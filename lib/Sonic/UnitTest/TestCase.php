<?php
namespace Sonic\UnitTest;
use Sonic\UnitTest\Result\Error;
use Sonic\UnitTest\Result\Failure;
use Sonic\UnitTest\Result\Success;

/**
 * TestCase
 *
 * @category Sonic
 * @package UnitTest
 * @author Craig Campbell
 */
abstract class TestCase
{
    /**
     * array of all failures for the current test class
     *
     * @var array
     */
    protected $_failures = array();

    /**
     * array of all errors for the current test class
     *
     * @var array
     */
    protected $_errors = array();

    /**
     * string of expected exception name when we are expecting an exception
     *
     * @var string
     */
    protected $_expected_exception;

    /**
     * determines if this test has a specific method
     *
     * @param string $method
     * @return bool
     */
    public final function hasMethod($method)
    {
        return method_exists($this, $method);
    }

    /**
     * gets a list of all methods for this class
     *
     * @return array
     */
    public final function getTestMethods()
    {
        return get_class_methods($this);
    }

    /**
     * logs a successful assertion
     *
     * @return void
     */
    protected final function _logSuccess()
    {
        $info = $this->_getInfoFromTrace(debug_backtrace(false));
        $success = new Success($info['method'], $info['file'], $info['line']);
        Tracker::addSuccess($success);
    }

    /**
     * logs a unit test failure
     *
     * @param string $message
     * @param array $info
     * @return void
     */
    protected final function _logFailure($message, $info = null)
    {
        if (!$info) {
            $info = $this->_getInfoFromTrace(debug_backtrace(false));
        }

        $this->_failures[] = $info['function'];
        $failure = new Failure($info['method'], $info['file'], $info['line']);
        $failure->setMessage($message);

        Tracker::addFailure($failure);
    }

    /**
     * logs a failure from on exception
     *
     * @param string $message
     * @param Exception $e
     * @return void
     */
    protected final function _logFailureFromException($message, \Exception $e)
    {
        $info = $this->_getInfoFromTrace($e->getTrace(), 0);

        $this->_failures[] = $info['function'];
        $failure = new Failure($info['method'], $info['file'], $info['line']);
        $failure->setMessage($message);

        Tracker::addFailure($failure);
    }

    /**
     * logs an error
     *
     * this method is public because it is called from the UnitTest\Runner class
     *
     * @param Error $error
     * @param string $function
     * @return void
     */
    public function logError(Error $error, $function)
    {
        $this->_errors[] = $function;
        Tracker::addError($error);
    }

    /**
     * gets information about the failure from the backtrace
     *
     * @param array $trace
     * @param int $level how many levels deep in the trace should we look
     * @return array
     */
    protected function _getInfoFromTrace(array $trace, $level = 1)
    {
        $info = array(
            'file' => $trace[$level]['file'],
            'line' => $trace[$level]['line'],
            'function' => $trace[$level + 1]['function'],
            'method' => $trace[$level + 1]['class'] . $trace[$level + 1]['type'] . $trace[$level + 1]['function'] . '()'
        );

        return $info;
    }

    /**
     * determines if a test passed
     *
     * @param string $function name of test method
     * @return bool
     */
    public final function passed($function)
    {
        return !$this->hadError($function) && !$this->hadFailure($function);
    }

    /**
     * determines if a test had an error
     *
     * @param string $function name of test method
     * @return bool
     */
    public final function hadError($function)
    {
        return in_array($function, $this->_errors);
    }

    /**
     * determines if a test had a failure
     *
     * @param string $function name of test method
     * @return bool
     */
    public final function hadFailure($function)
    {
        return in_array($function, $this->_failures);
    }

    /**
     * called when an exception is caught by the runner to see if the exception
     * was expected or not
     *
     * @param Exception $e
     * @return void
     */
    public final function caughtException(\Exception $e)
    {
        $type = get_class($e);
        if (!$this->_expected_exception) {
            $this->_logFailureFromException('unexpected exception: ' . $type . "\n      " . $e->getMessage(), $e);
            $this->_expected_exception = null;
            return;
        }

        if ($type != $this->_expected_exception) {
            $this->_logFailureFromException('exception class ' . $type . ' does not match class ' . $this->_expected_exception, $e);
            $this->_expected_exception = null;
            return;
        }

        $this->_expected_exception = null;
    }

    /**
     * notification sent by the runner when a test has finished running
     * this is so we can check if an exception was expected but never came
     *
     * @return void
     */
    public final function hasFinished()
    {
        if ($this->_expected_exception) {
            $this->_logFailure('expected exception: ' . $this->_expected_exception, $this->_expected_exception_info);
        }
    }

    /**
     * called before each assertion to see if we should stop running
     * if an assertion failed within this method then we should not continue
     * with other tests
     *
     * @return bool
     */
    protected final function _stop()
    {
        $current = Runner::getInstance()->current();
        return !$this->passed($current[1]);
    }

    /**
     * converts a variable to a string representation of the variable
     *
     * @param string
     * @return string
     */
    protected function _stringify($var)
    {
        switch (gettype($var)) {
            case 'boolean':
                return '(bool) ' . ($var == true ? 'true' : 'false');
                break;
            case 'NULL':
                return 'null';
                break;
            default:
                return $var;
        }
    }

    /**
     * sets expected exception type for the next part of the test
     *
     * @param string $type
     * @return void
     */
    public final function isException($type)
    {
        $this->_expected_exception = $type;
        $this->_expected_exception_info = $this->_getInfoFromTrace(debug_backtrace(false), 0);
    }

    /**
     * variable should be true
     *
     * @param mixed $var
     * @return void
     */
    public final function isTrue($var)
    {
        if ($this->_stop()) {
            return;
        }

        if ($var == true) {
            return $this->_logSuccess();
        }

        $this->_logFailure($this->_stringify($var) . ' is not (bool) true');
    }

    /**
     * variable should be false
     *
     * @param mixed $var
     * @return void
     */
    public final function isFalse($var)
    {
        if ($this->_stop()) {
            return;
        }

        if ($var == false) {
            return $this->_logSuccess();
        }

        $this->_logFailure($this->_stringify($var) . ' is not (bool) false');
    }

    /**
     * variable should be exactly equal to another variable
     *
     * @param mixed $first
     * @param mixed $second
     * @return void
     */
    public final function isExact($first, $second)
    {
        if ($this->_stop()) {
            return;
        }

        if ($first === $second) {
            return $this->_logSuccess();
        }

        $this->_logFailure($this->_stringify($first) . ' is not exactly the same as ' . $this->_stringify($second));
    }

    /**
     * variable should be equal to another variable
     *
     * @param mixed $first
     * @param mixed $second
     * @return void
     */
    public final function isEqual($first, $second)
    {
        if ($this->_stop()) {
            return;
        }

        if ($first == $second) {
            return $this->_logSuccess();
        }

        $this->_logFailure($this->_stringify($first) . ' does not equal ' . $this->_stringify($second));
    }

    /**
     * variable should not be equal to another variable
     *
     * @param mixed $first
     * @param mixed $second
     * @return void
     */
    public final function isNotEqual($first, $second)
    {
        if ($this->_stop()) {
            return;
        }

        if ($first != $second) {
            return $this->_logSuccess();
        }

        $this->_logFailure($this->_stringify($first) . ' is equal to ' . $this->_stringify($second));
    }

    /**
     * variable should be null
     *
     * @param mixed $var
     * @return void
     */
    public final function isNull($var)
    {
        if ($this->_stop()) {
            return;
        }

        if ($var === null) {
            return $this->_logSuccess();
        }

        $this->_logFailure($this->_stringify($var) . ' is not null');
    }

    /**
     * variable should not be null
     *
     * @param mixed $var
     * @return void
     */
    public final function isNotNull($var)
    {
        if ($this->_stop()) {
            return;
        }

        if ($var != null) {
            return $this->_logSuccess();
        }

        $this->_logFailure('variable is null');
    }

    /**
     * variable should be an array
     *
     * @param mixed $var
     * @return void
     */
    public final function isArray($var)
    {
        if ($this->_stop()) {
            return;
        }

        if (is_array($var)) {
            return $this->_logSuccess();
        }

        $this->_logFailure('variable is not array');
    }

    /**
     * variable should not be an array
     *
     * @param mixed $var
     * @return void
     */
    public final function isNotArray($var)
    {
        if ($this->_stop()) {
            return;
        }

        if (!is_array($var)) {
            return $this->_logSuccess();
        }

        $this->_logFailure('variable is array');
    }
}
