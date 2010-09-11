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
    protected $_raw_sql;
    protected $_sql;
    protected $_link;
    protected $_result;
    protected $_fetch_into_class;

    public function __construct($sql, $link)
    {
        $this->_raw_sql = $this->_sql = $sql;
        $this->_link = $link;
    }

    public function execute()
    {
        $this->_result = mysql_query($this->_sql, $this->_link);
        if ($this->_result === false) {
            throw new Exception(mysql_error());
        }
    }

    public function bindValue($key, $value)
    {
        $value = "'" . mysql_real_escape_string($value, $this->_link) . "'";
        $this->_sql = preg_replace('/' . $key . '\b/', $value, $this->_sql);
        return $this;
    }

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

    public function fetchObject($class)
    {
        $row = $this->fetch();
        $object = $this->_populateObjectFromRow(new $class, $row);
        $object->reset();
        return $object;
    }

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

    protected function _populateObjectFromRow($object, $row)
    {
        foreach ($row as $key => $value) {
            $object->$key = $value;
        }
        return $object;
    }

    public function fetch($type = null)
    {
        switch ($type) {
            case MySql2Pdo::FETCH_NUM:
                return mysql_fetch_row($this->_result);
            default:
                return mysql_fetch_assoc($this->_result);
        }
    }

    public function setFetchMode($mode, $class)
    {
        $this->_fetch_into_class = $class;
    }

    public function columnCount()
    {
        return mysql_num_fields($this->_result);
    }
}
