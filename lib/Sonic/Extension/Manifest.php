<?php
namespace Sonic\Extension;

/**
 * Manifest
 *
 * @category Sonic
 * @package Extension
 * @author Craig Campbell
 */
abstract class Manifest
{
    /**
     * @var string
     */
    const VERSION = '';

    /**
     * @var array
     */
    protected $_dependencies = array();

    /**
     * gets dependencies
     *
     * @return array
     */
    public function getDependencies()
    {
        return array_map('strtolower', $this->_dependencies);
    }
}
