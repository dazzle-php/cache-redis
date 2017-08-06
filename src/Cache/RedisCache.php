<?php

namespace Dazzle\Cache\Redis;

use Dazzle\Cache\CacheInterface;
use Dazzle\Event\BaseEventEmitter;
use Dazzle\Loop\LoopAwareTrait;
use Dazzle\Loop\LoopInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;
use Dazzle\Redis\Redis;
use Dazzle\Redis\RedisInterface;
use Dazzle\Throwable\Exception\Runtime\ReadException;
use Dazzle\Throwable\Exception\Runtime\WriteException;
use Error;
use Exception;

class RedisCache extends BaseEventEmitter implements CacheInterface
{
    use LoopAwareTrait;

    /**
     * @var mixed[]
     */
    protected $config;

    /**
     * @var RedisInterface
     */
    protected $redis;

    /**
     * @var int
     */
    protected $selected;

    /**
     * @param LoopInterface $loop
     * @param mixed[] $config
     */
    public function __construct(LoopInterface $loop, $config = [])
    {
        $this->loop = $loop;
        $this->config = $this->createConfig($config);
        $this->redis = $this->createRedis();
        $this->selected = 0;

        $this->attachEvents();
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->stop();
        parent::__destruct();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isStarted()
    {
        return $this->redis->isStarted();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function start()
    {
        return $this->redis->start()->then(function() { return $this; });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function stop()
    {
        return $this->redis->stop()->then(function() { return $this; });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function end()
    {
        return $this->redis->end()->then(function() { return $this; });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function isPaused()
    {
        return $this->redis->isPaused();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function pause()
    {
        return $this->redis->pause();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function resume()
    {
        return $this->redis->resume();
    }

    /**
     * @override
     * @inheritDoc
     */
    public function set($key, $val, $ttl = 0.0)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        if (is_object($val))
        {
            return Promise::doReject(new WriteException('Objects are not supported.'));
        }

        $promise = $ttl > 0
            ? $this->redis->setEx($key, round($ttl), json_encode($val))
            : $this->redis->set($key, json_encode($val));

        return $promise->then(function($result) use($val) {
            if ($result !== 'OK')
            {
                throw new WriteException('Value could not be set.');
            }
            return $val;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function get($key)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return $this->redis->get($key)->then(function($result) {
            return $result === null ? null : json_decode($result, true);
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function remove($key)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        return $this->redis->del($key)->then(function($count) {
            return $count ? true : false;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function exists($key)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return $this->redis->exists($key)->then(function($count) {
            return $count ? true : false;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function setTtl($key, $ttl)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        if ($ttl <= 0)
        {
            return Promise::doReject(new WriteException('TTL needs to be higher than 0.'));
        }
        return $this->redis->expire($key, round($ttl))->then(function($result) {
            if ($result === 0)
            {
                throw new WriteException('Timeout cannot be set on undefined key.');
            }
            return $result;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getTtl($key)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return $this->redis->ttl($key)->then(function($result) {
            return $result > 0 ? $result : 0;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function removeTtl($key)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        return $this->redis->persist($key)->then(function($result) {
            return $result > 0;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function existsTtl($key)
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return $this->redis->ttl($key)->then(function($result) {
            return $result >= 0;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getKeys()
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return $this->redis->keys()->then(function($result) {
            sort($result);
            return $result;
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function getStats()
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new ReadException('Cache object is not open.'));
        }
        return $this->redis->info()->then(function($result) {
            preg_match('#keys=([0-9]+)#si', $result['keyspace']['db' . $this->selected], $matches);
            $keys = isset($matches[1]) ? $matches[1] : 0;
            return [
                'keys'   => (int) $keys,
                'hits'   => (int) $result['stats']['keyspace_hits'],
                'misses' => (int) $result['stats']['keyspace_misses'],
            ];
        });
    }

    /**
     * @override
     * @inheritDoc
     */
    public function flush()
    {
        if (!$this->isStarted())
        {
            return Promise::doReject(new WriteException('Cache object is not open.'));
        }
        return $this->redis->flushDb();
    }

    /**
     * Create configuration.
     *
     * @return mixed[]
     */
    protected function createConfig($config = [])
    {
        return array_merge([ 'endpoint' => 'redis://127.0.0.1:6379' ], $config);
    }

    /**
     * Create Redis client.
     *
     * @return RedisInterface
     */
    protected function createRedis()
    {
        return new Redis($this->createEndpoint($this->config['endpoint']), $this->getLoop());
    }

    /**
     * Parse and return endpoint.
     *
     * @param string $endpoint
     * @return string
     */
    protected function createEndpoint($endpoint)
    {
        $endpoint = explode('://', $endpoint, 2);
        $endpoint = explode('/', $endpoint[1]);
        $endpoint = stripos($endpoint[0], ':') === false ? $endpoint[0] . ':6379' : $endpoint[0];
        $endpoint = 'tcp://' . $endpoint;
        return $endpoint;
    }

    /**
     * Attach events.
     */
    private function attachEvents()
    {
        $this->redis->on('start', function($redis) {
            $this->emit('start', [ $this ]);
        });
        $this->redis->on('stop', function($redis) {
            $this->emit('stop', [ $this ]);
        });
        $this->redis->on('error', function($redis, $ex) {
            $this->emit('error', [ $this, $ex ]);
        });
    }
}
