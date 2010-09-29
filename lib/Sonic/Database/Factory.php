<?php
namespace Sonic\Database;
use Sonic\App;

/**
 * Factory
 *
 * @category Sonic
 * @package Database
 * @author Craig Campbell
 * @version 1.0 beta
 */
class Factory
{
    /**
     * @var array
     */
    protected static $_databases = array();

    /**
     * @var array
     */
    protected static $_servers = array();

    /**
     * gets a database object
     *
     * @param string $schema
     * @return Database
     */
    public static function getDatabase($schema = null)
    {
        if (!isset(self::$_databases[$schema])) {
            self::$_databases[$schema] = new \Sonic\Database($schema);
        }
        return self::$_databases[$schema];
    }

    /**
     * gets all servers for a particular schema
     *
     * @param string $schema
     * @return array
     */
    public static function getServers($schema)
    {
        if (isset(self::$_servers[$schema])) {
            return self::$_servers[$schema];
        }

        $config = \Sonic\App::getConfig();
        if (!$servers = $config->get('db.' . $schema)) {
            throw new Exception('no database found matching schema: ' . $schema);
        }

        $user = $config->get('db.' . $schema . '.user');
        $password = $config->get('db.' . $schema . '.password');

        self::$_servers[$schema] = array();

        foreach ($servers as $server) {
            $data = self::_parseDsn($server, $schema);
            $data['user'] = $user;
            $data['password'] = $password;
            self::$_servers[$schema][] = $data;
        }

        return self::$_servers[$schema];
    }

    /**
     * parses a dsn string like  mysql:dbname=database;host=127.0.0.1;type=_r
     * and returns array('dbname' => 'database, 'host' => '127.0.0.1', 'type' => '_r')
     *
     * @param string
     * @param string
     * @return array
     */
    protected static function _parseDsn($dsn, $schema)
    {
        $bits = explode(':', $dsn);
        $vars = isset($bits[1]) ? $bits[1] : $bits[0];
        $vars = explode(';', $vars);

        $server = array();
        foreach ($vars as $var) {
            $bits = explode('=', $var);
            $server[$bits[0]] = $bits[1];
        }

        if (!isset($server['weight']))
            $server['weight'] = null;

        $server['dsn'] = 'mysql:dbname=' . $schema . ';host=' . $server['host'];

        return $server;
    }
}
