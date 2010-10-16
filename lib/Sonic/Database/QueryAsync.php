<?php
namespace Sonic\Database;

/**
 * QueryAsync
 *
 * a wrapper for Sonic\Database\Query used to run queries asynchronously
 *
 * note that this only works when using App::MYSQLI for your driver
 *
 * also note that this does not implement a lot of methods found in Query
 *
 * @category Sonic
 * @package Database
 * @subpackage Query
 * @author Craig Campbell
 */
class QueryAsync
{
    /**
     * @var Async
     */
    protected static $_async;

    /**
     * @var Query
     */
    protected $_query;

    /**
     * constructor
     *
     * @param string $sql
     * @param string $schema
     * @return void
     */
    public function __construct($sql, $schema = null)
    {
        $query = new Query($sql, $schema);
        $this->_query = $query;
        $this->_getAsync()->addQuery($query);
    }

    /**
     * gets the Database\Async object
     *
     * @return Async
     */
    protected function _getAsync()
    {
        if (self::$_async === null) {
            self::$_async = new Async();
        }
        return self::$_async;
    }

    /**
     * binds a value to this query
     *
     * @param string $key
     * @param mixed $value
     * @return Query
     */
    public function bindValue($key, $value)
    {
        return $this->_query->bindValue($key, $value);
    }

    /**
     * fetches all rows for this query
     *
     * @return array
     */
    public function fetchAll()
    {
        return $this->_getAsync()->fetchResultsForQuery($this->_query, Async::FETCH_ALL);
    }

    /**
     * fetches a single row for this query
     *
     * @return array
     */
    public function fetchRow()
    {
        return $this->_getAsync()->fetchResultsForQuery($this->_query, Async::FETCH_ROW);
    }

    /**
     * fetches a single value for this query
     *
     * @return mixed
     */
    public function fetchValue()
    {
        return $this->_getAsync()->fetchResultsForQuery($this->_query, Async::FETCH_VALUE);
    }
}
