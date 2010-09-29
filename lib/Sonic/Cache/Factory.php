<?php
namespace Sonic\Cache;
use Sonic\App;

/**
 * Factory
 *
 * @category Sonic
 * @package Cache
 * @author Craig Campbell
 */
class Factory
{
    /**
     * @var array
     */
    protected static $_caches = array();

    /**
     * @var array
     */
    protected static $_servers = array();

    /**
     * gets memcache object for a specific pool
     *
     * @param string $pool
     * @return Memcache
     */
    public static function getMemcache($pool = 'default')
    {
        if (isset(self::$_caches[$pool])) {
            return self::$_caches[$pool];
        }

        if (App::getInstance()->getSetting(App::DISABLE_CACHE)) {
            self::$_caches[$pool] = new \Sonic\Cache\Disabled();
            return self::$_caches[$pool];
        }

        $servers = self::getServers($pool);
        self::$_caches[$pool] = new Memcache($servers);

        return self::$_caches[$pool];
    }

    /**
     * gets memcache servers for a specific pool
     *
     * @param string $pool
     * @return array
     */
    public static function getServers($pool = 'default')
    {
        if (isset(self::$_servers[$pool])) {
            return self::$_servers[$pool];
        }

        $servers = App::getConfig()->get('cache.' . $pool);
        if ($servers === null) {
            throw new Exception('no servers found for cache pool: ' . $pool);
        }

        self::$_servers[$pool] = array();
        foreach ($servers as $server) {
            $bits = explode(':', $server);
            $server = array();
            $server['host'] = $bits[0];
            $server['port'] = $bits[1];

            self::$_servers[$pool][] = $server;
        }

        return self::$_servers[$pool];
    }
}
