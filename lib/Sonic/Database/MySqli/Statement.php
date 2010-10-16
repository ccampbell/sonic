<?php
namespace Sonic\Database\MySqli;
use Sonic\Database\MySql;
use Sonic\Database\MySqli;
use Sonic\Database\MySqli\Exception;

/**
 * Statement class
 *
 * @package MySqli
 * @subpackage Statement
 * @author Craig Campbell
 */
class Statement extends MySql\Statement
{
    /**
     * executes the given query
     *
     * @return void
     */
    public function execute($type = MYSQLI_STORE_RESULT)
    {
        $this->_result = mysqli_query($this->_link, $this->_sql, $type);
        if ($this->_result === false) {
            throw new Exception(mysqli_error($this->_link));
        }
        return $this->_result;
    }

    /**
     * binds a value to the query
     *
     * @param string $key
     * @param mixed $value
     */
    public function bindValue($key, $value)
    {
        $value = "'" . mysqli_real_escape_string($this->_link, $value) . "'";
        $this->_sql = preg_replace('/' . $key . '\b/', $value, $this->_sql);
        return $this;
    }

    /**
     * fetches a bunch of rows
     *
     * @return array
     */
    public function fetchAll()
    {
        if ($this->_fetch_into_class) {
            return $this->_fetchIntoClass($this->_fetch_into_class);
        }

        $rows = array();
        while ($row = mysqli_fetch_assoc($this->_result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * fetches a bunch of rows into newly created objects
     *
     * @param string $class class to use for the object creation
     * @return array
     */
    protected function _fetchIntoClass($class)
    {
        $objects = array();
        while ($row = mysqli_fetch_assoc($this->_result)) {
            $object = new $class;
            $object = $this->_populateObjectFromRow($object, $row);
            $object->reset();
            $objects[] = $object;
        }
        return $objects;
    }

    /**
     * fetches a single row
     *
     * @param int $type
     * @return array
     */
    public function fetch($type = null)
    {
        switch ($type) {
            case MySqli::FETCH_NUM:
                return mysqli_fetch_row($this->_result);
            default:
                return mysqli_fetch_assoc($this->_result);
        }
    }

    /**
     * gets the count of the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {
        return mysqli_num_fields($this->_result);
    }
}
