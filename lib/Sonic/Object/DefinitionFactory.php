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
        include $path . '/definitions.php';
        self::$_definitions = $definitions;

        return self::$_definitions;
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
}
