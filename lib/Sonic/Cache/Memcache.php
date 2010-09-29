<?php
namespace Sonic\Cache;
use Memcache as MC;
use Sonic\Util;

/**
 * Memcache
 *
 * @category Sonic
 * @package Cache
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
     * multigets an array of keys from cache
     *
     * @param array $keys
     * @return array
     */
    public function getMulti(array $keys, $preserve_order = true)
    {
        // grab whatever items we can from cache
        $items = $this->get($keys);

        // if we don't care what order the keys are returned in
        if (!$preserve_order) {
            return $items;
        }

        // set all keys to null so if something is not found in cache it won't
        // be set to a value
        $results = array_fill_keys($keys, null);

        // merge the two arrays so the returned values from cache overwrite
        // the starting values
        return array_merge($results, $items);
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
