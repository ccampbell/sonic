<?php
namespace Sonic;
use Sonic\Database\Query;
use Sonic\Database\QueryCached;
use Sonic\App;

/**
 * Object
 *
 * @category Sonic
 * @package Object
 * @author Craig Campbell
 */
abstract class Object
{
    /**
     * @var int
     */
    const MURDERED = 345181800;

    /**
     * @var int
     */
    protected $id;

    /**
     * @var array
     */
    protected $_object_vars;

    /**
     * @var array
     */
    protected $_updates = array();

    /**
     * @var array
     */
    protected $_cache_times = array();

    /**
     * @var array
     */
    protected static $_unique_properties;

    /**
     * gets a property for this object
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        $this->_verifyProperty($property);
        return $this->$property;
    }

    /**
     * sets a property of this object
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function __set($property, $value)
    {
        $this->_verifyProperty($property);
        $current_value = $this->$property;

        if ($value === $current_value) {
            return;
        }

        $this->$property = $value;

        $this->_updates[$property] = $property;
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

    /**
     * gets the definition for this object
     *
     * @return array
     */
    public static function getDefinition()
    {
        $class = get_called_class();
        return Object\DefinitionFactory::getDefinition($class);
    }

    /**
     * gets unique indexed properties for this object
     *
     * @return array
     */
    public final static function getUniqueProperties()
    {
        if (self::$_unique_properties !== null) {
            return self::$_unique_properties;
        }

        $unique = array();
        $definition = self::getDefinition();
        foreach ($definition['columns'] as $key => $column) {
            if (!isset($column['indexed'])) {
                continue;
            }

            if (!isset($column['unique'])) {
                continue;
            }

            if ($column['indexed'] && $column['unique']) {
                $unique[] = $key;
            }
        }
        self::$_unique_properties = $unique;

        return self::$_unique_properties;
    }

    /**
     * gets a cache time based on an identifier like "last_update"
     *
     * @param string
     * @return int
     */
    public function getCacheTime($key)
    {
        if (!isset($this->_cache_times[$key])) {
            return $this->setCacheTime($key);
        }
        return $this->_cache_times[$key];
    }

    /**
     * sets a cache time based on an identifier
     *
     * @param string
     * @return int
     */
    public function setCacheTime($key)
    {
        $this->_cache_times[$key] = time();
        $this->_cache();
        return $this->_cache_times[$key];
    }

    /**
     * gets an object or a bunch of objects
     *
     * @param mixed $id value to get
     * @param string $column column to get that value from
     * @return Object
     */
    public final static function get($key, $value = null)
    {
        if ($value === null) {
            $value = $key;
            $key = 'id';
        }

        if (!is_array($value)) {
            return self::_getSingle($key, $value);
        }

        if (count($value) == 0) {
            return array();
        }

        if ($key != 'id') {
            throw new Object\Exception('you can only multiget an object by id');
        }

        // if this is an array of one return it as an array of one
        if (count($value) == 1) {
            $value = array_pop($value);
            $object = self::_getSingle('id', $value);
            return array($object);
        }

        return self::_getMultiple($value);
    }

    /**
     * gets multiple objects by ids
     *
     * @todo implement
     */
    protected static function _getMultiple(array $ids)
    {
        // first build an array of cache keys
        $cache_keys = $cache_key_to_id = array();
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                throw new Object\Exception('all ids must be numeric');
            }
            $cache_key = self::_getCacheKey('id', $id);
            $cache_keys[] = $cache_key;
            $cache_key_to_id[$cache_key] = $id;
        }

        $cache = App::getMemcache();

        // do a multiget request for these cache keys
        $objects = $cache->getMulti($cache_keys);

        // figure out what is missing
        $missing = array();
        foreach ($objects as $cache_key => $object) {
            if ($object instanceof Object) {
                continue;
            }

            // if we have stored in cache that this object doesn't exist at all
            // let's not make a trip to the database for it, just return it as null
            if ($object == self::MURDERED) {
                $objects[$cache_key] = null;
                continue;
            }

            $missing[] = $cache_key_to_id[$cache_key];
        }

        // everything was found in cache
        if (count($missing) == 0) {
            return array_values($objects);
        }

        // we have to go to the database
        $definition = self::getDefinition();
        $sql = "SELECT `id`, `" . implode('`, `', array_keys($definition['columns'])) . '` FROM `' . $definition['table'] . '` WHERE id IN (' . implode(',', $missing) . ')';
        $query = new Query($sql, $definition['schema']);
        $objects_db = $query->fetchObjects(get_called_class());

        // use this array to map ids to cache keys instead of calling Object::_getCacheKey() over and over
        $id_to_cache_key = array_flip($cache_key_to_id);

        // loop through the objects that were found in the database and index them by cache key
        // this is so we can merge them with the original cache keys and keep the order intact
        // also set them to cache so future lookups won't have to hit the database
        $found = array();
        foreach ($objects_db as $object) {
            $cache_key = $id_to_cache_key[$object->id];
            $found[$cache_key] = $object;
            $object->_cache();
            $cache->set($cache_key, $object, '1 week');
        }

        // merge what we found in the database into what we found in cache
        $objects = array_merge($objects, $found);

        // should we check for soft deletes
        $soft_deletes = isset($definition['columns']['is_deleted']);

        // anything not found in cache and not found in the database should be set in cache
        // this is so we don't keep trying to find it everytime it was requested
        // could be a deleted object that is still cached as part of a collection
        foreach ($objects as $cache_key => $object) {
            if (!$object instanceof Object || ($soft_deletes && $object->is_deleted)) {
                $cache->set($cache_key, self::MURDERED, '1 week');
                $objects[$cache_key] = null;
            }
        }

        return array_values($objects);
    }

    /**
     * gets a single object
     *
     * @param mixed $value
     * @param string $column
     * @return Object
     */
    protected static function _getSingle($column, $value)
    {
        $class = get_called_class();
        $definition = self::getDefinition($class);

        // get these exceptions out of the way
        if ($column != 'id' && !isset($definition['columns'][$column])) {
            throw new Object\Exception('object of class: ' . $class . ' does not have a property called: ' . $column);
        }

        if ($column != 'id' && !$definition['columns'][$column]['indexed'] && !$definition['columns'][$column]['unique']) {
            throw new Object\Exception('column: ' . $column . ' in class ' . $class . ' has to be unique and indexed to load using it!');
        }

        // preliminary query to get the id if we are selecting based on another column
        if ($column != 'id') {
            $sql = "SELECT `id` FROM `" . $definition['table'] . '` WHERE `' . $column . '` = :' . $column . ' LIMIT 1';
            $query = new QueryCached($sql, self::_getCacheKey($column, $value), '1 week', $definition['schema']);
            $query->bindValue(':' . $column, $value);
            $id = $query->fetchValue();

            // now we can select it as usual
            $column = 'id';
            $value = $id;
        }

        // valid select
        $sql = "SELECT `id`, `" . implode('`, `', array_keys($definition['columns'])) . '` FROM `' . $definition['table'] . '` WHERE `' . $column . '` = ' . ':' . $column;
        $query = new QueryCached($sql, self::_getCacheKey($column, $value), '1 week', $definition['schema']);
        $query->bindValue(':' . $column, $value);

        $object =  $query->fetchObject($class);

        if (!$object) {
            return null;
        }

        // if this is a soft deleted object then don't return it
        if (isset($definition['columns']['is_deleted']) && $object->is_deleted == 1) {
            return null;
        }

        return $object;
    }

    /**
     * saves or updates an object
     *
     * @return void
     */
    public function save()
    {
        if ($this->id && count($this->_updates) == 0) {
            return;
        }

        $definition = $this->getDefinition();

        // set default values
        foreach ($definition['columns'] as $property => $column) {

            // if this column is set to NOW we need to put the date in for cache
            if ($this->$property === 'NOW()') {
                $this->$property = date('Y-m-d H:i:s');
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

        // this is a new object
        if (!$this->id || in_array('id', $this->_updates)) {
            $this->_add();
            $this->reset();
            $this->_cache();
            return;
        }

        // this is an object being updated
        $this->_update();
        $this->reset();
        $this->_cache();
    }

    /**
     * adds an object to the database
     *
     * @return bool
     */
    protected function _add()
    {
        $definition = $this->getDefinition();
        $sql = 'INSERT INTO `' . $definition['table'] . '` (`' . implode('`, `', $this->_updates) . '`) VALUES (:' . implode(', :', $this->_updates) . ')';
        $query = new Database\Query($sql, $definition['schema']);
        foreach ($this->_updates as $column) {
            $query->bindValue(':' . $column, $this->$column);
        }
        $result = $query->execute();

        if (!$result) {
            return false;
        }

        $this->id = $query->lastInsertId();

        // go through all the unique properties and link them in cache to this id
        foreach ($this->getUniqueProperties() as $property) {
            App::getMemcache()->set(self::_getCacheKey($property, $this->$property), $this->id, '1 week');
        }

        return true;
    }

    /**
     * updates an object in the database
     *
     * @return bool
     */
    protected function _update()
    {
        if (in_array('id', $this->_updates)) {
            throw new Object\Exception('you cannot update an id after you create an object');
        }

        $definition = $this->getDefinition();
        $sql = "UPDATE `" . $definition['table'] . '` SET ';
        $i = 0;
        foreach ($this->_updates as $column) {
            if ($i > 0) {
                $sql .= ', ';
            }
            $sql .= '`' . $column . '` = :' . $column;
            ++$i;
        }
        $sql .= ' WHERE id = :current_id';
        $query = new Query($sql, $definition['schema']);
        foreach ($this->_updates as $column) {
            $query->bindValue(':' . $column, $this->$column);
        }
        $query->bindValue(':current_id', $this->id);

        foreach ($this->_updates as $update) {
            if (in_array($update, $this->getUniqueProperties())) {
                App::getMemcache()->set(self::_getCacheKey($update, $this->$update), $this->id, '1 week');
            }
        }

        return $query->execute();
    }

    /**
     * permanently delete an object from the database
     *
     * WARNING!!! BE CAREFUL WITH THIS!!!
     *
     * you can easily use soft deletes by adding an "is_deleted" column in your definitions
     *
     * @return bool
     */
    public function delete()
    {
        $definition = $this->getDefinition();
        $sql = "DELETE FROM `" . $definition['table'] . "` WHERE id = :id";
        $query = new Query($sql, $definition['schema']);
        $query->bindValue(':id', $this->id);
        $result = $query->execute();

        if ($result) {
            $cache_key = $this->_getCacheKey('id', $this->id);
            App::getMemcache()->set($cache_key, self::MURDERED, '1 week');
        }

        return $result;
    }

    /**
     * resets object properties that shouldn't be set in cache
     *
     * @return void
     */
    public function reset()
    {
        $definition = $this->getDefinition();
        $columns = array_keys($definition['columns']);
        foreach ($this->_getObjectVars() as $var => $value) {
            if ($var != 'id' && $var != '_cache_times' && !in_array($var, $columns)) {
                $this->$var = null;
            }
        }
        $this->_updates = array();
    }

    /**
     * gets cache key for this object
     *
     * @param string $column
     * @param mixed $value
     * @return string
     */
    protected final static function _getCacheKey($column, $value)
    {
        $definition = self::getDefinition();
        return $definition['table'] . '_' . $column . ':' . $value;
    }

    /**
     * puts this object into cache
     *
     * @return void
     */
    protected function _cache()
    {
        $cache_key = self::_getCacheKey('id', $this->id);
        App::getMemcache()->set($cache_key, $this, '1 week');
    }
}
