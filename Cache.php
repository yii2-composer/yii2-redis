<?php
/**
 * User: liyifei
 * Date: 16/10/26
 */
namespace liyifei\redis;

use Redis;
use yii\base\InvalidConfigException;
use yii\di\Instance;

class Cache extends \yii\caching\Cache
{
    /**
     * @var \Redis
     */
    public $redis='redis';

    /**
     * Initializes the redis Cache component.
     * This method will initialize the [[redis]] property to make sure it refers to a valid redis connection.
     * @throws InvalidConfigException if [[redis]] is invalid.
     */
    public function init()
    {
        parent::init();
        $this->redis = Instance::ensure($this->redis, Connection::className());
    }

    /**
     * Checks whether a specified key exists in the cache.
     * This can be faster than getting the value from the cache if the data is big.
     * Note that this method does not check whether the dependency associated
     * with the cached data, if there is any, has changed. So a call to [[get]]
     * may return false while exists returns true.
     * @param mixed $key a key identifying the cached value. This can be a simple string or
     * a complex data structure consisting of factors representing the key.
     * @return boolean true if a value exists in cache, false if the value is not in the cache or expired.
     */
    public function exists($key)
    {
        return (bool)$this->redis->exists($this->buildKey($key));
    }

    /**
     * @inheritdoc
     */
    protected function getValue($key)
    {
        return unserialize($this->redis->get($key));
    }

    /**
     * @inheritdoc
     */
    protected function getValues($keys)
    {
        $response = $this->redis->mget($keys);
        $result = [];
        $i = 0;
        foreach ($keys as $key) {
            $result[$key] = unserialize($response[$i++]);
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    protected function setValue($key, $value, $expire)
    {
        $value = serialize($value);
        if ($expire == 0) {
            return (bool)$this->redis->set($key,$value);
        } else {
            $expire = (int)($expire * 1000);

            return (bool)$this->redis->set($key,$value,['px'=>$expire]);
        }
    }

    /**
     * @inheritdoc
     */
    protected function setValues($data, $expire)
    {
        $args = [];
        foreach ($data as $key => $value) {
            $args[] = $key;
            $args[] = serialize($value);
        }

        $failedKeys = [];
        if ($expire == 0) {
            $this->redis->mset($args);
        } else {
            $expire = (int)($expire * 1000);
            $this->redis->multi(Redis::PIPELINE);
            $this->redis->mset($args);
            $index = [];
            foreach ($data as $key => $value) {
                $this->redis->pExpire($key,$expire);
                $index[] = $key;
            }
            $result = $this->redis->exec();
            array_shift($result);
            foreach ($result as $i => $r) {
                if ($r != 1) {
                    $failedKeys[] = $index[$i];
                }
            }
        }

        return $failedKeys;
    }

    /**
     * @inheritdoc
     */
    protected function addValue($key, $value, $expire)
    {
        $value = serialize($value);
        if ($expire == 0) {
            return (bool)$this->redis->setnx($key, $value);
        } else {
            $expire = (int)($expire * 1000);
            
            return (bool)$this->redis->set($key,$value,['px'=>$expire,'nx']);
        }
    }

    /**
     * @inheritdoc
     */
    protected function deleteValue($key)
    {
        return (bool)$this->redis->del($key);
    }

    /**
     * @inheritdoc
     */
    protected function flushValues()
    {
        return (bool)$this->redis->flushDB();
    }
}
