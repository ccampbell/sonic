<?php
namespace Sonic;

/**
 * Object
 *
 * @package Sonic
 * @subpackage Object
 * @author Craig Campbell
 */
abstract class Object
{
    protected $id;
    protected $_object_vars;
    protected $_updates = array();

    public function __get($property)
    {
        $this->_verifyProperty($property);
        return $this->$property;
    }

    public function __set($property, $value)
    {
        $this->_verifyProperty($property);
        $current_value = $this->$property;

        if ($value === $current_value) {
            return;
        }

        $this->$property = $value;

        if (!in_array($property, $this->_updates)) {
            $this->_updates[] = $property;
        }
    }

    /**
     * verifies a property exists or throws an exception
     *
     * @param string $property
     * @return void
     */
    protected final function _verifyProperty($property)
    {
        if (!$this->_propertyExists($property)) {
            throw new Object\Exception('property ' . $property . ' does not exist in object: ' . get_class($this));
        }
    }

    /**
     * gets object vars
     *
     * @return array
     */
    protected final function _getObjectVars()
    {
        if ($this->_object_vars === null) {
            $this->_object_vars = get_object_vars($this);
        }
        return $this->_object_vars;
    }

    /**
     * checks if a property exists in this object
     *
     * @param string $property name of property
     * @return bool
     */
    protected final function _propertyExists($property)
    {
        return array_key_exists($property, $this->_getObjectVars());
    }

    public static function getDefinition()
    {
        $class = get_called_class();
        return Object\DefinitionFactory::getDefinition($class);
    }

    public function save()
    {
        if (count($this->_updates) == 0) {
            return;
        }

        $definition = $this->getDefinition();

        // fields set to NOW()
        $now_fields = array();

        // set default values
        foreach ($definition['columns'] as $property => $column) {

            // if this column is set to NOW we need to put the date in for cache
            if ($this->$property == 'NOW()') {
                $this->$property = date('Y-m-d H:i:s');
                $now_fields[] = $property;
                continue;
            }

            // no default
            if (!isset($column['default'])) {
                continue;
            }

            // property already set
            if (in_array($property, $this->_updates)) {
                continue;
            }

            if (!$this->$property) {
                $this->$property = $column['default'];
            }
        }

        if (!$this->id || in_array('id', $this->_updates)) {
            $this->_addToDatabase($now_fields);
            $this->_reset();
            // $this->_cache();
        }

        var_dump($this);
    }

    protected function _reset()
    {
        $definition = $this->getDefinition();
        $columns = array_keys($definition['columns']);
        foreach ($this->_getObjectVars() as $var => $value) {
            if ($var != 'id' && !in_array($var, $columns)) {
                $this->$var = null;
            }
        }
        $this->_updates = array();
    }

    protected final function _addToDatabase(array $now_fields)
    {
        $definition = $this->getDefinition();
        $sql = 'INSERT INTO `' . $definition['table'] . '` (`' . implode('`, `', $this->_updates) . '`) VALUES (:' . implode(', :', $this->_updates) . ')';
        $query = new Database\Query($sql, $definition['schema']);
        foreach ($this->_updates as $column) {
            $query->bindValue(':' . $column, $this->$column);
        }
        $query->execute();

        $this->id = $query->lastInsertId();
    }
}
