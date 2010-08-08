<?php
namespace Sonic\Cache;
use Memcache as MC;
use Sonic\Util;

/**
 * Memcache
 *
 * @package Cache
 * @subpackage Memcache
 * @author Craig Campbell
 */
class Memcache
{
    /**
     * @var Memcache
     */
    protected $_memcache;

    /**
     * constructs a new Memcache class
     *
     * pass in an array formatted like this:
     *
     * array(
     *     0 => array('host' => '127.0.0.1', 'port' => '11211'),
     *     1 => array('host' => '127.0.0.1', 'port' => '11311')
     * )
     *
     * @param array
     * @return void
     */
    public function __construct(array $servers)
    {
        $this->_memcache = new MC();
        foreach ($servers as $server) {
            $this->_addServer($server['host'], $server['port']);
        }
    }

    /**
     * adds a server to the pool
     *
     * @param string $host
     * @param int $port
     */
    protected function _addServer($host, $port)
    {
        try {
            $this->_memcache->addServer($host, $port);
        } catch (\Exception $e) {

        }
    }

    /**
     * sets a key in cache
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function set($key, $value, $ttl = 7200)
    {
        $ttl = Util::toSeconds($ttl);
        return $this->_memcache->set($key, $value, 0, $ttl);
    }

    /**
     * gets a key from cache
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->_memcache->get($key);
    }

    /**
     * deletes a key from cache
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        return $this->_memcache->delete($key);
    }

    /**
     * gets memcache object
     *
     * @return Memcache
     */
    public function getMemcache()
    {
        return $this->_memcache;
    }
}
