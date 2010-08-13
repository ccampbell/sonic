<?php
namespace Sonic;

/**
 * Pager
 *
 * @package Sonic
 * @subpackage Pager
 * @author Craig Campbell
 */
class Pager
{
    /**
     * current page number
     *
     * @var int
     */
    protected $_page;

    /**
     * total number of items per page
     *
     * @var int
     */
    protected $_page_size;

    /**
     * total number of items in the data set
     *
     * @var int
     */
    protected $_total;

    /**
     * total number of pages
     *
     * @var int
     */
    protected $_total_pages;

    /**
     * first item on current page
     *
     * @var int
     */
    protected $_first_on_page;

    /**
     * last item on current page
     *
     * @var int
     */
    protected $_last_on_page;

    /**
     * constructor
     *
     * @param int $page page number
     * @param int $page_size number of items per page
     * @return void
     */
    public function __construct($page , $page_size)
    {
        $this->_page = $page;
        $this->_page_size = $page_size;
    }

    /**
     * sets the total number of items in this data set
     *
     * usually comes from some kind of count query in the database
     *
     * @param int $number
     * @return void
     */
    public function setTotal($count)
    {
        $this->_total = $count;

        // the first item on this page will be one above the offset
        $this->_first_on_page = $this->getOffset() + 1;

        // total number of pages
        $this->_total_pages = ceil($count / $this->_page_size);

        // if the total number of items is greater than the first item
        // and there are more pages
        if ($count > $this->_first_on_page && $this->_total_pages > $this->_page) {
            $this->_last_on_page = $this->getOffset() + $this->_page_size;
            return;
        }

        $this->_last_on_page = $count;
    }

    /**
     * determine the offset
     *
     * @return int
     */
    public function getOffset()
    {
        return ($this->_page - 1) * $this->_page_size;
    }

    /**
     * determines if there is a previous page
     *
     * @return bool
     */
    public function hasPrevious()
    {
        return $this->pageExists($this->_page - 1);
    }

    /**
     * gets the previous page
     *
     * @return int
     */
    public function getPrevious()
    {
        if ($this->hasPrevious()) {
            return $this->_page - 1;
        }
        return false;
    }

    /**
     * determines if there is a next page
     *
     * @return bool
     */
    public function hasNext()
    {
        return $this->pageExists($this->_page + 1);
    }

    /**
     * gets the next page
     *
     * @return int
     */
    public function getNext()
    {
        if ($this->hasNext()) {
            return $this->_page + 1;
        }
        return false;
    }

    /**
     * determines if the specified page exists
     *
     * @param int
     * @return bool
     */
    public function pageExists($page)
    {
        if ($page <= 0) {
            return false;
        }

        if ($page > $this->_total_pages) {
            return false;
        }

        return true;
    }

    /**
     * gets the total number of items on the current page
     *
     * @return int
     */
    public function getCurrentPageSize()
    {
        return $this->_last_on_page - $this->_first_on_page + 1;
    }

    /**
     * gets the current page size
     *
     * @return int
     */
    public function getPageSize()
    {
        return $this->_page_size;
    }

    /**
     * gets the total number of pages
     *
     * @return int
     */
    public function getTotalPages()
    {
        return $this->_total_pages;
    }

    /**
     * gets the first item on the current page
     *
     * @return int
     */
    public function getFirstOnPage()
    {
        return $this->_first_on_page;
    }

    /**
     * gets the last item on the current page
     *
     * @return int
     */
    public function getLastOnPage()
    {
        return $this->_last_on_page;
    }

    /**
     * gets the current page number
     *
     * @return int
     */
    public function getPage()
    {
        return $this->_page;
    }

    /**
     * gets the total number of items
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->_total;
    }
}
