<?php
namespace Sonic\Object;
use Sonic\App;

/**
 * DefinitionFactory
 *
 * @package Object
 * @subpackage DefinitionFactory
 * @author Craig Campbell
 */
class DefinitionFactory
{
    /**
     * @var array
     */
    protected static $_definitions;

    /**
     * @var string
     */
    protected static $_database_name;

    /**
     * @var array
     */
    protected static $_tables;

    /**
     * gets all object definitions
     *
     * @return array
     */
    public static function getDefinitions()
    {
        if (self::$_definitions !== null) {
            return self::$_definitions;
        }
        $path = App::getInstance()->getPath('configs');
        include $path . DIRECTORY_SEPARATOR . 'definitions.php';
        self::$_definitions = $definitions;

        return self::$_definitions;
    }

    /**
     * gets database name for definitions
     *
     * @return string
     */
    public static function getDatabase()
    {
        if (self::$_database_name !== null) {
            return self::$_database_name;
        }
        self::getDefinitions();
        return self::$_database_name;
    }

    /**
     * gets a single object definition
     *
     * @var string $class
     * @return array
     */
    public static function getDefinition($class)
    {
        $definitions = self::getDefinitions();
        if (!isset($definitions[$class])) {
            throw new \Sonic\Exception('no definitions found for class: ' . $class);
        }

        return $definitions[$class];
    }

    /**
     * gets all ORM tables
     *
     * @return array
     */
    public static function getTables()
    {
        if (self::$_tables !== null) {
            return self::$_tables;
        }

        $definitions = self::getDefinitions();
        self::$_tables = array();
        foreach ($definitions as $key => $definition) {
            self::$_tables[$key] = $definition['table'];
        }

        return self::$_tables;
    }
}
