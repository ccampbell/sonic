<?php
namespace Sonic;
use \ArrayObject;

/**
 * Collection
 *
 * @category Sonic
 * @package Collection
 * @author Craig Campbell
 * @version 1.0 beta
 */
class Collection extends ArrayObject
{
    /**
     * @var array
     */
    protected $_ids;

    /**
     * @var string
     */
    protected $_object_name;

    /**
     * @var Pager
     */
    protected $_pager;

    /**
     * @var bool
     */
    protected $_filled = false;

    /**
     * constructor
     *
     * @param string $object_name
     * @param array $ids
     * @param Pager $pager
     * @return void
     */
    public function __construct($object_name = null, $ids = array(), Pager $pager = null)
    {
        $this->_object_name = $object_name;
        $this->_ids = $ids;
        $this->_pager = $pager;

        if ($pager instanceof Pager) {
            $this->_pager->setTotal(count($ids));
        }

        unset($ids);
    }

    /**
     * gets the count of this collection
     *
     * @return int
     */
    public function count()
    {
        // if there is a parent count that means this collection is being used as a normal ArrayObject
        if (parent::count() && empty($this->_ids)) {
            return parent::count();
        }

        return count($this->_ids);
    }

    /**
     * overrides parent getIterator method
     * used to lazy load collection and not actually retrieve it until you go to loop over it
     *
     * @return Iterator
     */
    public function getIterator()
    {
        if (parent::count()) {
            return parent::getIterator();
        }

        $this->_fillCollection();
        return parent::getIterator();
    }

    /**
     * fills the collection if it hasn't already been fille
     *
     * @return void
     */
    protected function _fillCollection()
    {
        if ($this->_filled) {
            return;
        }

        $ids = $this->_ids;
        if ($this->_pager instanceof Pager) {
            $ids = array_slice($ids, $this->_pager->getOffset(), $this->_pager->getPageSize());
        }

        $object_name = $this->_object_name;
        $objects = $object_name::get($ids);

        $this->exchangeArray($objects);

        $this->_filled = true;
    }

    /**
     * gets the pager object
     *
     * @return Pager
     */
    public function getPager()
    {
        return $this->_pager;
    }
}
