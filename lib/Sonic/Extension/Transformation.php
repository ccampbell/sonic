<?php
namespace Sonic\Extension;

/**
 * Transformation class
 *
 * allows you to dynamically add methods to certain classes at runtime
 *
 * @category Sonic
 * @package Transformation
 * @author Craig Campbell
 */
class Transformation
{
    /**
     * prefix to avoid namespace conflicts
     *
     * @var string
     */
    const PREFIX = 'trnsfmtn_';

    /**
     * array of static methods
     *
     * @var array
     */
    protected $_static_methods = array();

    /**
     * array of regular methods
     *
     * @var array
     */
    protected $_methods = array();

    /**
     * array of transformation objects
     *
     * @param array
     */
    protected static $_transformations = array();

    /**
     * gets a transformation object for the specified class
     *
     * @param string $name
     * @return Transformation
     */
    public static function get($name)
    {
        if (!isset(self::$_transformations[$name])) {
            self::$_transformations[$name] = new Transformation();
        }
        return self::$_transformations[$name];
    }

    /**
     * calls a method via transformation
     *
     * @param string $name
     * @param array $args
     * @param string $class
     */
    public static function call($name, $args, $class, $class_name = null)
    {
        $t = self::get($class);
        if ($t->hasMethod($name)) {
            return $t->callMethod($name, $args);
        }

        $class_name = $class_name ?: $class;
        trigger_error('Call to undefined method ' . $class_name . '::' . $name . '()', E_USER_ERROR);
    }

    /**
     * calls a static method via transformation
     *
     * @param string $name
     * @param array $args
     * @param string $class
     */
    public static function callStatic($name, $args, $class, $class_name = null)
    {
        $t = self::get($class);
        if ($t->hasStaticMethod($name)) {
            return $t->callStaticMethod($name, $args);
        }

        $class_name = $class_name ?: $class;
        trigger_error('Call to undefined method ' . $class_name . '::' . $name . '()', E_USER_ERROR);
    }

    /**
     * adds a static method
     *
     * @param string $name
     * @param Closure $function
     * @return Transformation
     */
    public function addStaticMethod($name, $function)
    {
        $this->_static_methods[self::PREFIX . $name] = $function;
        return $this;
    }

    /**
     * adds a method
     *
     * @param string $name
     * @param Closure $function
     * @return Transformation
     */
    public function addMethod($name, $function)
    {
        $this->_methods[self::PREFIX . $name] = $function;
        return $this;
    }

    /**
     * determines if this static method exists
     *
     * @param string $name
     * @return bool
     */
    public function hasStaticMethod($name)
    {
        return isset($this->_static_methods[self::PREFIX . $name]);
    }

    /**
     * determines if this method exists
     *
     * @param string $name
     * @return bool
     */
    public function hasMethod($name)
    {
        return isset($this->_methods[self::PREFIX . $name]);
    }

    /**
     * calls static method
     *
     * @param string $name
     * @return mixed
     */
    public function callStaticMethod($name, array $args)
    {
        return call_user_func_array($this->_static_methods[self::PREFIX . $name], $args);
    }

    /**
     * calls method
     *
     * @param string $name
     * @return mixed
     */
    public function callMethod($name, array $args)
    {
        return call_user_func_array($this->_methods[self::PREFIX . $name], $args);
    }
}
