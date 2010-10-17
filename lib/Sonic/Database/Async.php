<?php
namespace Sonic\Database;
use Sonic\Database\Query, Sonic\App;

/**
 * Async database class
 *
 * This allows you to execute multiple queries asynchronously when using mysqli
 *
 * @category Sonic
 * @package Database
 * @author Craig Campbell
 */
class Async
{
    const FETCH_ROW = 1;
    const FETCH_ALL = 2;
    const FETCH_VALUE = 3;
    const EXECUTE = 4;

    /**
     * @var array
     */
    protected $_queries = array();

    /**
     * @var array
     */
    protected $_results;

    /**
     * adds a query to run
     *
     * @param Query
     */
    public function addQuery(Query $query)
    {
        if ($this->_results !== null && count($this->_results) == count($this->_queries)) {
            $this->_queries = array();
            $this->_results = null;
        }
        $this->_queries[] = $query;
    }

    /**
     * determines if we should use asynchronous queries
     *
     * @return bool
     */
    protected function _useAsync()
    {
        if (App::getInstance()->getSetting(App::DB_DRIVER) != App::MYSQLI) {
            return false;
        }

        if (count($this->_queries) < 2) {
            return false;
        }

        return true;
    }

    /**
     * fetches results from all the queries
     *
     * @param int $fetch_mode
     * @return array
     */
    public function fetchResults()
    {
        if ($this->_results !== null) {
            return $this->_results;
        }

        if (!$this->_useAsync()) {
            throw new Query\Exception('not in async mode.  use Async::fetchResultsForQuery() instead');
        }

        $results = array();
        $all_links = $this->_getLinksFromQueries($this->_queries);
        $processed = 0;
        while ($processed < count($all_links)) {
            $links = $errors = $reject = array();

            foreach ($all_links as $link) {
                $links[] = $errors[] = $reject[] = $link;
            }

            if (!mysqli_poll($links, $errors, $reject, 1)) {
                continue;
            }

            foreach ($links as $link) {
                if (!$result = mysqli_reap_async_query($link)) {
                    continue;
                }

                $results[] = $result;
                ++$processed;
            }
        }
        $this->_results = $results;
        unset($results);

        return $this->_results;
    }

    /**
     * fetch results for a single query
     *
     * @param Query $query
     * @param int $fetch_mode
     * @return array
     */
    public function fetchResultsForQuery(Query $query, $fetch_mode = self::FETCH_ALL)
    {
        if (!$this->_useAsync()) {
            return $this->_getResultFromQuery($query, $fetch_mode);
        }

        $key = array_search($query, $this->_queries);

        if ($key === false) {
            if ($fetch_mode == self::FETCH_ALL) {
                return array();
            }
            return null;
        }

        $results = $this->fetchResults($fetch_mode);
        $result = $this->_getResultFromFetchMode($results[$key], $fetch_mode);

        mysqli_free_result($results[$key]);
        return $result;
    }

    /**
     * takes a mysqli result object and a fetch mode and returns the results
     *
     * @param mysqli_result $result
     * @param int $fetch_mode
     * @return mixed
     */
    protected function _getResultFromFetchMode(\mysqli_result $result, $fetch_mode)
    {
        switch ($fetch_mode) {
            case self::FETCH_ROW:
                return mysqli_fetch_assoc($result);
                break;
            case self::FETCH_VALUE:
                $row = mysqli_fetch_assoc($result);
                return array_pop($row);
                break;
            case self::EXECUTE:
                break;
            default:
                $field_count = mysqli_num_fields($result);
                $rows = array();
                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $field_count == 1 ? array_pop($row) : $row;
                }
                return $rows;
                break;
        }
    }

    /**
     * gets a result directly from the query object
     *
     * this is what happens if the driver doesn't support asynchronous queries
     * or it doesn't make sense to run asynchronous queries
     *
     * @param Query $query
     * @param int $fetch_mode
     * @return mixed
     */
    protected function _getResultFromQuery(Query $query, $fetch_mode)
    {
        switch ($fetch_mode) {
            case self::FETCH_VALUE:
                return $query->fetchValue();
                break;
            case self::FETCH_ALL:
                return $query->fetchAll();
                break;
            case self::FETCH_ROW:
                return $query->fetchRow();
                break;
            case self::EXECUTE:
                return $query->execute();
                break;
        }
    }

    /**
     * gets mysqli links for all of these queries
     *
     * @param array $queries
     * @return array
     */
    protected function _getLinksFromQueries(array $queries)
    {
        $links = array();
        foreach ($queries as $query) {
            $links[] = $this->_getLinkFromQuery($query);
        }
        return $links;
    }

    /**
     * gets a link from a single query
     *
     * @param Query $query
     * @return Object
     */
    protected function _getLinkFromQuery(Query $query)
    {
        $params = $query->getBoundParams();
        $statement = $query->getStatement();
        foreach ($params as $key => $value) {
            $statement->bindValue($key, $value);
        }
        $statement->execute(MYSQLI_ASYNC);
        return $statement->getLink();
    }
}
