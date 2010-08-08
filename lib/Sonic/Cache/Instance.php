<?php
namespace Sonic\Cache;

/**
 * Instance Cache
 *
 * Stores variables used on this request
 *
 * @package Cache
 * @subpackage Instance
 * @author Craig Campbell
 */
class Instance
{
    /**
     * @var array
     */
    protected static $_storage = array();

    /**
     * sets something in instance cache
     *
     * @var string $key
     * @var mixed $value
     * @return bool
     */
    public static function set($key, $value)
    {
        self::$_storage[$key] = $value;
        return true;
    }

    /**
     * gets something from instance cache
     *
     * @var string $key
     * @return mixed
     */
    public static function get($key)
    {
        if (array_key_exists($key, self::$_storage)) {
            return self::$_storage[$key];
        }
        return null;
    }

    /**
     * removes something from instance cache
     *
     * @var string $key
     * @return bool
     */
    public static function delete($key)
    {
        if (self::get($key) !== null) {
            unset(self::$_storage[$key]);
            return true;
        }
        return false;
    }
}
