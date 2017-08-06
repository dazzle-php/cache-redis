<?php

namespace Dazzle\Cache\Redis\Test\TUnit;

use Dazzle\Cache\Cache;
use Dazzle\Cache\CacheInterface;
use Dazzle\Cache\Redis\RedisCache;
use Dazzle\Cache\Test\TUnit;
use Dazzle\Loop\Loop;
use Dazzle\Loop\LoopInterface;
use Dazzle\Promise\Promise;
use Dazzle\Promise\PromiseInterface;
use Dazzle\Redis\Redis;
use Dazzle\Redis\RedisInterface;

class RedisCacheTest extends TUnit
{
    /**
     * @var RedisInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $client;

    /**
     * @var CacheInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $cache;

    /**
     *
     */
    public function testApiConstructor_CreatesProperInstance()
    {
        $cache = $this->createCache();
        $this->assertInstanceOf(CacheInterface::class, $cache);
    }

    /**
     *
     */
    public function testApiDestructor_DoesNotThrow()
    {
        $cache = $this->createCache();
        unset($cache);
    }

    /**
     *
     */
    public function testApiSet_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->set('KEY', 'VAL')->isRejected());
    }

    /**
     *
     */
    public function testApiGet_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->get('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiRemove_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->remove('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiExists_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->exists('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiSetTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->setTtl('KEY', 1)->isRejected());
    }

    /**
     *
     */
    public function testApiGetTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->getTtl('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiRemoveTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->removeTtl('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiExistsTtl_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->existsTtl('KEY')->isRejected());
    }

    /**
     *
     */
    public function testApiGetKeys_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->getKeys()->isRejected());
    }

    /**
     *
     */
    public function testApiGetStats_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->getStats()->isRejected());
    }

    /**
     *
     */
    public function testApiFlush_RejectsPromise_WhenCacheIsNotStarted()
    {
        $cache = $this->createCache([ 'client' => [ 'isStarted' ] ]);
        $this->client
            ->expects($this->once())
            ->method('isStarted')
            ->will($this->returnValue(false));
        $this->assertTrue($cache->flush()->isRejected());
    }

    /**
     * @param string[]|null $methods
     * @return Loop|LoopInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function createLoop($methods = null)
    {
        return $this->getMock(Loop::class, $methods, [], '', false);
    }

    /**
     * @param string[]|null $methods
     * @param LoopInterface $loop
     * @return Cache|CacheInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    public function createCache($methods = [], LoopInterface $loop = null)
    {
        $loop = $loop === null ? $this->createLoop() : $loop;
        $methods = [
            'cache'  => isset($methods['cache'])  ? $methods['cache']  : null,
            'client' => isset($methods['client']) ? $methods['client'] : null,
        ];
        $this->client = $this->getMock(Redis::class, $methods['client'], [ 'tcp://127.0.0.1:6379', $loop ], '', false);
        $this->cache  = $this->getMock(RedisCache::class, $methods['cache'], [ $loop ], '', false);
        $this->setProtectedProperty($this->cache, 'redis', $this->client);

        return $this->cache;
    }
}
