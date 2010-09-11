<?php
namespace Sonic\Database\MySql2Pdo;
use Sonic\Database\MySql2Pdo;
use Sonic\Database\MySql2Pdo\Exception;

/**
 * Statement class
 *
 * @package MySql2Pdo
 * @subpackage Statement
 * @author Craig Campbell
 */
class Statement
{
    /**
     * @var string
     */
    protected $_raw_sql;

    /**
     * @var string
     */
    protected $_sql;

    /**
     * @var string
     */
    protected $_link;

    /**
     * @var mixed
     */
    protected $_result;

    /**
     * @var string
     */
    protected $_fetch_into_class;

    /**
     * constructor
     *
     * @param string $sql
     * @param string $link
     */
    public function __construct($sql, $link)
    {
        $this->_raw_sql = $this->_sql = $sql;
        $this->_link = $link;
    }

    /**
     * executes the given query
     *
     * @return void
     */
    public function execute()
    {
        $this->_result = mysql_query($this->_sql, $this->_link);
        if ($this->_result === false) {
            throw new Exception(mysql_error());
        }
    }

    /**
     * binds a value to the query
     *
     * @param string $key
     * @param mixed $value
     */
    public function bindValue($key, $value)
    {
        $value = "'" . mysql_real_escape_string($value, $this->_link) . "'";
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
        while ($row = mysql_fetch_assoc($this->_result)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * fetches a single object
     *
     * @param string $class class to use for the object creation
     * @return Object
     */
    public function fetchObject($class)
    {
        $row = $this->fetch();
        $object = $this->_populateObjectFromRow(new $class, $row);
        $object->reset();
        return $object;
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
        while ($row = mysql_fetch_assoc($this->_result)) {
            $object = new $class;
            $object = $this->_populateObjectFromRow($object, $row);
            $object->reset();
            $objects[] = $object;
        }
        return $objects;
    }

    /**
     * populates an object from a database row
     *
     * @param Object $object
     * @param array $row
     * @return Object
     */
    protected function _populateObjectFromRow($object, $row)
    {
        foreach ($row as $key => $value) {
            $object->$key = $value;
        }
        return $object;
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
            case MySql2Pdo::FETCH_NUM:
                return mysql_fetch_row($this->_result);
            default:
                return mysql_fetch_assoc($this->_result);
        }
    }

    /**
     * sets fetch mode for this query
     *
     * @param int
     * @param string
     * @return void
     */
    public function setFetchMode($mode, $class)
    {
        if ($mode = MySql2Pdo::FETCH_CLASS) {
            $this->_fetch_into_class = $class;
        }
    }

    /**
     * gets the count of the number of columns in the result set
     *
     * @return int
     */
    public function columnCount()
    {
        return mysql_num_fields($this->_result);
    }
}
