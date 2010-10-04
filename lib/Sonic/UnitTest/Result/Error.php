<?php
namespace Sonic\UnitTest\Result;
use Sonic\UnitTest\Result;

/**
 * Error
 *
 * @category Sonic
 * @package UnitTest
 * @subpackage Result
 * @author Craig Campbell
 */
class Error extends Result
{
    /**
     * @var string
     */
    protected $_message;

    /**
     * sets error message
     *
     * @param string
     * @return void
     */
    public function setMessage($message)
    {
        $this->_message = $message;
    }

    /**
     * gets error message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->_message;
    }
}
