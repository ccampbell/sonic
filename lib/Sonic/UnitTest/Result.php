<?php
namespace Sonic\UnitTest;

/**
 * Result
 *
 * @category Sonic
 * @package UnitTest
 * @author Craig Campbell
 */
abstract class Result
{
    /**
     * @var int
     */
    protected $_line;

    /**
     * @var string
     */
    protected $_method;

    /**
     * @var string
     */
    protected $_file;

    /**
     * constructor
     *
     * @param string
     * @param string
     * @param int
     * @return void
     */
    public function __construct($method, $file, $line)
    {
        $this->_method = $method;
        $this->_file = $file;
        $this->_line = $line;
    }

    /**
     * gets line number
     *
     * @return int
     */
    public function getLine()
    {
        return $this->_line;
    }

    /**
     * gets method
     *
     * @return string
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * gets file path
     *
     * @return string
     */
    public function getFile()
    {
        return $this->_file;
    }
}
