<?php
namespace Sonic\Database;
use Sonic\Util;
use Sonic\App;

/**
 * Cached Query Class
 *
 * @package Database
 * @subpackage QueryCached
 * @author Craig Campbell
 */
class QueryCached extends Query
{
    protected $_cache_key;
    protected $_cache_time;
    protected $_in_cache;
    protected $_cached_value;

    /**
     * constructor
     *
     * @param string $sql
     * @param string $schema
     * @return void
     */
    public function __construct($sql, $cache_key, $time = 7200, $schema = 'main')
    {
        parent::__construct($sql, $schema);
        $this->_cache_key = $cache_key;
        $this->_cache_time = Util::toSeconds($time);
    }

    /**
     * determines if this query is in cache or not
     *
     * @return bool
     */
    public function wasInCache()
    {
        if ($this->_in_cache !== null) {
            return $this->_in_cache;
        }

        $cache = App::getMemcache();
        $data = $cache->get($this->_cache_key);

        if ($data === false) {
            $this->_in_cache = false;
            return false;
        }

        $this->_cached_value = $data;
        $this->_in_cache = true;
        return true;
    }

    /**
     * caches data for this cache key
     *
     * @param mixed $data
     * @return void
     */
    protected function _cache($data)
    {
        App::getMemcache()->set($this->_cache_key, $data, $this->_cache_time);
        $this->_cached_value = $data;
    }

    /**
     * fetch value
     *
     * @return mixed
     */
    public function fetchValue()
    {
        if (!$this->wasInCache()) {
            $this->_cache(parent::fetchValue());
        }
        return $this->_cached_value;
    }

    /**
     * fetch row
     *
     * @return mixed
     */
    public function fetchRow()
    {
        if (!$this->wasInCache()) {
            $this->_cache(parent::fetchRow());
        }
        return $this->_cached_value;
    }

    /**
     * fetch object
     *
     * @return Object
     */
    public function fetchObject($class)
    {
        if (!$this->wasInCache()) {
            $this->_cache(parent::fetchObject($class));
        }
        return $this->_cached_value;
    }

    /**
     * fetch all
     *
     * @return array
     */
    public function fetchAll()
    {
        if (!$this->wasInCache()) {
            $this->_cache(parent::_fetchAll());
        }
        $results = $this->_cached_value;
        $results = $this->_filter($results);
        $results = $this->_sort($results);

        return $results;
    }
}
