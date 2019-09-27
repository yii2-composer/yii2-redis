<?php
/**
 * User: liyifei
 * Date: 16/10/26
 */

namespace liyifei\redis;

use Redis;
use yii\base\Component;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class Connection
 * @package liyifei\redis
 */
class Connection extends Component
{
    /**
     * @var string the hostname or ip address to use for connecting to the redis server. Defaults to 'localhost'.
     * If [[unixSocket]] is specified, hostname and port will be ignored.
     */
    public $hostname = 'localhost';
    /**
     * @var integer the port to use for connecting to the redis server. Default port is 6379.
     * If [[unixSocket]] is specified, hostname and port will be ignored.
     */
    public $port = 6379;
    /**
     * @var string the unix socket path (e.g. `/var/run/redis/redis.sock`) to use for connecting to the redis server.
     * This can be used instead of [[hostname]] and [[port]] to connect to the server using a unix socket.
     * If a unix socket path is specified, [[hostname]] and [[port]] will be ignored.
     * @since 2.0.1
     */
    public $unixSocket;
    /**
     * @var string the password for establishing DB connection. Defaults to null meaning no AUTH command is send.
     * See http://redis.io/commands/auth
     */
    public $password;
    /**
     * @var integer the redis database to use. This is an integer value starting from 0. Defaults to 0.
     */
    public $database = 0;

    public $persistent = false;

    /**
     * @desc all key prefix
     * @var string
     */
    public $prefix = '';

    /**
     * @var Redis
     */
    private $_connection = false;

    public function init()
    {
        if (!extension_loaded('redis')) {
            throw new InvalidConfigException("phpredis is required, see https://github.com/phpredis/phpredis.");
        }
    }

    public function open()
    {
        if ($this->_connection !== false) {
            return;
        }
        $this->_connection = new \Redis();
        $connected = false;
        if ($this->persistent) {
            if ($this->unixSocket) {
                $connected = $this->_connection->pconnect($this->unixSocket);
            } else {
                $connected = $this->_connection->pconnect($this->hostname, $this->port, 1);
            }
        } else {
            if ($this->unixSocket) {
                $connected = $this->_connection->connect($this->unixSocket);
            } else {
                $connected = $this->_connection->connect($this->hostname, $this->port, 1);
            }
        }
        if (!$connected) {
            throw new InvalidConfigException("fail to connect to redis");
        }
        if ($this->password) {
            $this->_connection->auth($this->password);
        }
        if ($this->database > 0) {
            $this->_connection->select($this->database);
        }
        $this->_connection->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_NONE);
        if ($this->prefix) {
            $this->_connection->setOption(Redis::OPT_PREFIX, $this->prefix);
        }

    }

    public function getIsActive()
    {
        return $this->_connection !== false;
    }

    public function close()
    {
        $this->_connection->close();
    }

    public function __call($name, $params)
    {
        $this->open();
        try {
            return call_user_func_array(array(&$this->_connection, $name), $params);
        } catch (ErrorException $e) {
            return parent::__call($name, $params);
        }
    }

    /**
     * @return Redis
     */
    public function pipline()
    {
        return $this->multi(Redis::PIPELINE);
    }
}
