<?php
namespace Sonic\Database;
use PDO;
use Sonic\Database;
use Sonic\Database\Query\Filter;
use Sonic\Database\Query\Sort;

/**
 * Query
 *
 * @package Database
 * @subpackage Query
 * @author Craig Campbell
 */
class Query
{
    /**
     * @var string
     */
    protected $_schema;

    /**
     * @var string
     */
    protected $_sql;

    /**
     * @var array
     */
    protected $_binds = array();

    /**
     * @var bool
     */
    protected $_executed = false;

    /**
     * @var PDOStatement
     */
    protected $_statement;

    /**
     * @var Query\Filter
     */
    protected $_filter;

    /**
     * @var Query\Sort
     */
    protected $_sort;

    /**
     * constructor
     *
     * @param string $sql
     * @param string $schema
     * @return void
     */
    public function __construct($sql, $schema = 'main')
    {
        $this->_sql = $sql;
        $this->_schema = $schema;
    }

    /**
     * gets the sql for this query
     *
     * @return string
     */
    public function getSql()
    {
        return $this->_sql;
    }

    /**
     * gets the PDOStatement for this query
     *
     * @return PDOStatement
     */
    public function getStatement()
    {
        if ($this->_statement !== null) {
            return $this->_statement;
        }

        $database = Factory::getDatabase($this->_schema);
        $this->_statement = $database->prepare($this->_sql);
        return $this->_statement;
    }

    /**
     * gets last insert id
     *
     * @return int
     */
    public function lastInsertId()
    {
        return (int) Factory::getDatabase($this->_schema)->getPdo(Database::MASTER)->lastInsertId();
    }

    /**
     * executes this query
     *
     * @return bool
     */
    public function execute()
    {
        $this->_executed = true;

        $statement = $this->getStatement();

        foreach ($this->_binds as $key => $value) {
            $statement->bindValue($key, $value);
        }

        return $statement->execute();
    }

    /**
     * gets a single value from the database
     *
     * @return mixed
     */
    public function fetchValue()
    {
        if (!$this->_executed)
            $this->execute();

        $row = $this->getStatement()->fetch(PDO::FETCH_NUM);
        return $row[0];
    }

    /**
     * gets a single row from the database
     *
     * @return array
     */
    public function fetchRow()
    {
        if (!$this->_executed)
            $this->execute();

        return $this->getStatement()->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * internal fetch all function to just return the database values
     * if there is only one column selected that is returned as an array
     *
     * @return array
     */
    protected function _fetchAll()
    {
        if (!$this->_executed) {
            $this->execute();
        }

        $results = $this->getStatement()->fetchAll(PDO::FETCH_ASSOC);

        if ($this->getStatement()->columnCount() != 1) {
            return $results;
        }

        // only one column
        $new_results = array();
        foreach ($results as $result)
            $new_results[] = array_pop($result);

        return $new_results;
    }

    /**
     * gets all rows from database that match
     * if there is only one column selected that is returned as an array
     *
     * @return array
     */
    public function fetchAll()
    {
        $results = $this->_fetchAll();
        $results = $this->_filter($results);
        $results = $this->_sort($results);
        return $results;
    }

    /**
     * gets all ids from the database that match
     *
     * @return array
     */
    public function fetchIds()
    {
        $all_data = $this->fetchAll();

        // no data to begin with
        if (count($all_data) == 0) {
            return array();
        }

        // no id column in the data
        if (!isset($all_data[0]['id'])) {
            return $all_data;
        }

        $ids = array();
        foreach ($all_data as $data) {
            $ids[] = (int) $data['id'];
        }
        return $ids;
    }

    /**
     * binds a parameter to this query
     *
     * @param string $key
     * @param mixed $value
     * @return Query
     */
    public function bindValue($key, $value)
    {
        if (array_key_exists($key, $this->_binds)) {
            throw new Exception('You have already bound ' . $key . ' to this query.');
        }
        $this->_binds[$key] = $value;
        return $this;
    }

    /**
     * adds a filtering pattern
     * for example: "id < 5" or "console = nintendo" or "id IN 1,2,3"
     *
     * @param string $pattern
     * @return Query
     */
    public function filter($pattern)
    {
        if ($this->_filter === null) {
            $this->_filter = new Filter();
        }

        $this->_filter->addPattern($pattern);
        return $this;
    }

    /**
     * adds a sorting pattern
     *
     * @param string $column
     * @param string $direction
     * @param bool $preserve_data
     * @return Query
     */
    public function sort($column, $direction, $preserve_data = false)
    {
        if ($this->_sort === null) {
            $this->_sort = new Sort();
        }
        $this->_sort->add($column, $direction, $preserve_data);
        return $this;
    }

    /**
     * processes applied filters
     *
     * @param array $data
     * @return array
     */
    protected function _filter($data)
    {
        if ($this->_filter === null) {
            return $data;
        }
        return $this->_filter->process($data);
    }

    /**
     * processes applied sorts
     *
     * @param array $data
     * @return array
     */
    protected function _sort($data)
    {
        if ($this->_sort === null) {
            return $data;
        }
        return $this->_sort->process($data);
    }
}
