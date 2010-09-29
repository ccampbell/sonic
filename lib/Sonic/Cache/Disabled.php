<?php
namespace Sonic\Cache;

/**
 * Disabled
 *
 * @category Sonic
 * @package Cache
 * @author Craig Campbell
 */
class Disabled
{
    /**
     * sets a key in cache
     *
     * @return void
     */
    public function set() {}

    /**
     * gets a key from cache
     *
     * @return false
     */
    public function get()
    {
        return false;
    }

    /**
     * multigets an array of keys from cache
     *
     * @param array $keys
     * @return array
     */
    public function getMulti(array $keys)
    {
        return array_fill_keys($keys, null);
    }

    /**
     * deletes a key from cache
     *
     * @return void
     */
    public function delete() {}

    /**
     * gets memcache object
     *
     * @return void
     */
    public function getMemcache() {}
}
