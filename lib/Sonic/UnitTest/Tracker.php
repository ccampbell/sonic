<?php
namespace Sonic\UnitTest;
use Sonic\UnitTest\Result\Error;
use Sonic\UnitTest\Result\Failure;
use Sonic\UnitTest\Result\Success;

/**
 * Tracker
 *
 * Used to track failures, successes, errors, and coverage for the the entire
 * test run
 *
 * @category Sonic
 * @package UnitTest
 * @author Craig Campbell
 */
class Tracker
{
    /**
     * @var Tracker
     */
    protected static $_instance;

    /**
     * @var array
     */
    protected $_failures = array();

    /**
     * @var array
     */
    protected $_successes = array();

    /**
     * @var array
     */
    protected $_errors = array();

    /**
     * @var array
     */
    protected $_coverages = array();

    /**
     * @var int
     */
    protected $_test_count = 0;

    /**
     * constructor
     *
     * @return void
     */
    private function __construct() {}

    /**
     * gets Tracker instance
     *
     * @return Tracker
     */
    public static function getInstance()
    {
        if (!self::$_instance instanceof Tracker) {
            self::$_instance = new Tracker();
        }
        return self::$_instance;
    }

    /**
     * adds a failure for the run
     *
     * @param Failure
     * @return void
     */
    public static function addFailure(Failure $failure)
    {
        $results = self::getInstance();
        $results->_failures[] = $failure;
    }

    /**
     * adds an error for the run
     *
     * @param Error
     * @return void
     */
    public static function addError(Error $error)
    {
        $results = self::getInstance();
        $results->_errors[] = $error;
    }

    /**
     * adds a success for the run
     *
     * @param Success
     */
    public static function addSuccess(Success $success)
    {
        $results = self::getInstance();
        $results->_successes[] = $success;
    }

    /**
     * increments the test count when a new test gets run
     *
     * @return void
     */
    public function incrementTestCount()
    {
        ++$this->_test_count;
    }

    /**
     * gets the total number of assertions
     *
     * @return int
     */
    public function getAssertionCount()
    {
        return count($this->_successes) + count($this->_failures) + count($this->_errors);
    }

    /**
     * gets an array of all failures
     *
     * @return array
     */
    public function getFailures()
    {
        return $this->_failures;
    }

    /**
     * gets an array of all errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->_errors;
    }

    /**
     * outputs stats to the command line
     *
     * @return void
     */
    public function outputStats()
    {
        $this->output("\n\nRESULTS\n", 'white', 'black', true);
        $this->output('Tests: ' . $this->_test_count . "\n");
        $this->output('Assertions: ' . $this->getAssertionCount() . "\n");
        $this->output('Failures: ' . count($this->_failures) . "\n");
        $this->output('Errors: ' . count($this->_errors) . "\n");
    }

    /**
     * outputs failures to command line
     *
     * @return void
     */
    public function outputFailures()
    {
        $failures = '';
        foreach ($this->getFailures() as $key => $failure) {
            $i = $key + 1;
            $failures .= $i . '.    ' . $failure->getMessage() . "\n";
            $failures .= '      ' . $failure->getMethod() . "\n";
            $failures .= '      ' . $failure->getFile() . "\n";
            $failures .= '      Line ' . $failure->getLine() . "\n";
        }
        return $this->output($failures);
    }

    /**
     * outputs errors to command line
     *
     * @return void
     */
    public function outputErrors()
    {
        $errors = '';
        foreach ($this->getErrors() as $key => $error) {
            $i = $key + 1;
            $errors .= $i . '.    ' . $error->getMessage() . "\n";
            $errors .= '      ' . $error->getMethod() . "\n";
            $errors .= '      ' . $error->getFile() . "\n";
            $errors .= '      Line ' . $error->getLine() . "\n";
        }
        return $this->output($errors);
    }

    /**
     * outputs a message to command line
     *
     * @todo make background color work
     * @param string $message
     * @param string $color
     * @param string $background
     * @param bool $bold
     * @return void
     */
    public function output($message, $color = null, $background = null, $bold = false)
    {
        $bold = $bold ? 1 : 0;

        $start = "\033[{$bold};";
        switch ($color) {
            case 'black':
                $start .= '30m';
                break;
            case 'red':
                $start .= '31m';
                break;
            case 'green':
                $start .= '32m';
                break;
            case 'yellow':
                $start .= '33m';
                break;
            case 'blue':
                $start .= '34m';
                break;
            case 'pink':
                $start .= '35m';
                break;
            case 'light blue':
                $start .= '36m';
                break;
            default:
                $start .= '37m';
                break;
        }
        echo $start . $message;
        echo "\033[0;37m";
    }

    /**
     * adds a coverage report to the tracker
     *
     * @param Coverage $coverage
     * @return void
     */
    public function addCoverage(Coverage $coverage)
    {
        $this->_coverages[] = $coverage;
    }

    /**
     * gets all coverage reports for the run
     *
     * @return array
     */
    public function getCoverages()
    {
        return $this->_coverages;
    }

    /**
     * gets total lines of code that can be tested/run
     *
     * @return int
     */
    public function getTotalLines()
    {
        $lines = 0;
        foreach ($this->getCoverages() as $coverage) {
            $lines += $coverage->getLineCount();
        }
        return $lines;
    }

    /**
     * gets total lines of code that have been run
     *
     * @return int
     */
    public function getCoveredLines()
    {
        $lines = 0;
        foreach ($this->getCoverages() as $coverage) {
            $lines += $coverage->getCoveredLineCount();
        }
        return $lines;
    }

    /**
     * gets total coverage for all files processed
     *
     * @return float
     */
    public function getTotalCoverage()
    {
        return round(($this->getCoveredLines() / $this->getTotalLines()) * 100, 2);
    }
}
