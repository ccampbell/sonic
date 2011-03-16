<?php
namespace Sonic\UnitTest;

/**
 * Runner
 *
 * Singleton class for running all the unit tests
 *
 * @category Sonic
 * @package UnitTest
 * @author Craig Campbell
 */
class Runner
{
    /**
     * @var Runner
     */
    protected static $_instance;

    /**
     * path to the directory containing the tests we are running
     *
     * @var string
     */
    protected $_directory;

    /**
     * single file we are going to process
     *
     * @var SplFileInfo
     */
    protected $_file;

    /**
     * path to directory to store the coverage report in
     *
     * @var string
     */
    protected $_coverage_directory;

    /**
     * stores the current TestCase object and method
     *
     * @var array
     */
    protected $_current;

    /**
     * args that were passed in via command line
     *
     * @var array
     */
    protected $_args = array();

    /**
     * used to store the coverage per file before we create Coverage objects
     *
     * @todo move this out into some sort of Coverage Tracker object
     * @var array
     */
    protected $_coverage = array();

    /**
     * name of bootstrap file
     *
     * @var string
     */
    const BOOTSTRAP = '_bootstrap.php';

    /**
     * constructor
     *
     * @return void
     */
    private function __construct()
    {
        $this->_loadFramework();
    }

    /**
     * gets instance of Runner
     *
     * @return Runner
     */
    public static function getInstance()
    {
        if (!self::$_instance instanceof Runner) {
            self::$_instance = new Runner();
        }
        return self::$_instance;
    }

    /**
     * loads the unit test framework
     *
     * @return void
     */
    protected function _loadFramework()
    {
        include 'Exception.php';
        include 'Coverage.php';
        include 'Result.php';
        include 'Result/Error.php';
        include 'Result/Failure.php';
        include 'Result/Success.php';
        include 'TestCase.php';
        include 'Tracker.php';
    }

    /**
     * starts running the unit tests
     *
     * @param array $args argumnts passed in via command line
     * @return void
     */
    public static function start($args = array())
    {
        $runner = self::getInstance();

        // store the args locally and process them
        $runner->_args = $args;
        $runner->_processArgs($args);

        // set up an error handler so warnings and errors can be caught
        set_error_handler(array($runner, 'handleError'));

        if ($runner->showCoverage()) {
            xdebug_start_code_coverage(XDEBUG_CC_UNUSED);
        }

        if ($runner->hasFile(self::BOOTSTRAP)) {
            $runner->includeFile(self::BOOTSTRAP);
        }

        $runner->runTests($runner->_file);

        $runner->showResults();
    }

    /**
     * processes the command line arguments
     *
     * @param array $args
     * @return void
     */
    protected function _processArgs($args = array())
    {
        if (count($args) == 1) {
            throw new Exception('no test directory specified');
        }

        $dir = array_pop($args);

        if (!is_dir($dir) && !file_exists($dir)) {
            throw new Exception('invalid test directory specified: ' . $dir);
        }

        // single file
        if (!is_dir($dir) && file_exists($dir)) {
            $path = $this->_convertDirectoryToPath($dir);
            $this->_file = new \SplFileInfo($path);
            $this->directory($this->_file->getPath());
        }

        if (is_dir($dir)) {
            $directory = $this->_convertDirectoryToPath($dir);
            $this->directory($directory);
        }

        $coverage_directory = $this->_getArgValue('--coverage-html', $args);
        if (!$coverage_directory) {
            return;
        }

        if (!file_exists($coverage_directory)) {
            mkdir($coverage_directory);
        }
        $path = $this->_convertDirectoryToPath($coverage_directory);
        $this->coverageDirectory($path);
    }

    /**
     * handles runtime errors that occur while the tests are running
     *
     * @param int $type
     * @param string $message
     * @param string $file
     * @param int $line
     * @return void
     */
    public function handleError($type, $message, $file, $line)
    {
        $current = $this->current();
        $method = get_class($current[0]) . '->' . $current[1] . '()';
        $error = new Result\Error($method, $file, $line);
        $error->setMessage($message);
        switch ($type) {
            case E_NOTICE:
                return;
                break;
            default:
                if ($current[0] instanceof TestCase) {
                    $current[0]->logError($error, $current[1]);
                }
                break;
        }
    }

    /**
     * determines if we should show coverage on this run
     *
     * @return bool
     */
    public function showCoverage()
    {
        if (!extension_loaded('xdebug')) {
            return false;
        }

        $valid = array('--coverage', '--coverage-html');
        foreach ($valid as $arg) {
            if (in_array($arg, $this->_args)) {
                return true;
            }
        }
        return false;
    }

    /**
     * determines if this file exists in the tests directory
     *
     * @param string $filename
     * @return bool
     */
    public function hasFile($filename)
    {
        return file_exists($this->directory() . '/' . $filename);
    }

    /**
     * includes a file
     *
     * @param string $filename
     * @return void
     */
    public function includeFile($filename)
    {
        include_once $this->directory() . '/' . $filename;
    }

    /**
     * gets an argument value based on argument name
     *
     * @param string $var
     * @return mixed
     */
    protected function _getArgValue($var)
    {
        $args = $this->_args;

        if (!in_array($var, $args)) {
            return;
        }

        $key = array_search($var, $args);
        if ($key === false) {
            return null;
        }

        if (isset($args[$key + 1])) {
            return $args[$key + 1];
        }

        return null;
    }

    /**
     * converts a relative path passed in via command line to an absolute path
     *
     * @param string $directory
     * @return string
     */
    protected function _convertDirectoryToPath($directory)
    {
        $info = pathinfo($directory);
        $full_path = realpath($info['dirname'] . '/' . $info['basename']);
        return $full_path;
    }

    /**
     * sets or gets coverage directory for html output of code coverage
     *
     * @param string $dir
     * @return string
     */
    public function coverageDirectory($dir = null)
    {
        if ($dir) {
            $this->_coverage_directory = $dir;
        }
        return $this->_coverage_directory;
    }

    /**
     * sets or gets the directory we are currently running tests in
     *
     * @param string $dir
     * @return string
     */
    public function directory($dir = null)
    {
        if ($dir) {
            $this->_directory = $dir;
        }
        return $this->_directory;
    }

    /**
     * runs the tests
     *
     * @return void
     */
    public function runTests(\SplFileInfo $file = null)
    {
        $tracker = Tracker::getInstance();

        $sonic = " ____    ___   _   _  ___  ____\n/ ___|  / _ \ | \ | ||_ _|/ ___|\n\___ \ | | | ||  \| | | || |\n ___) || |_| || |\  | | || |___\n|____/  \___/ |_| \_||___|\____|";

        $tracker->output($sonic, 'yellow');
        $tracker->output(" Unit Test Framework\n\n", 'yellow');

        if ($file) {
            return $this->processFile($file);
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->directory()));
        foreach ($it as $file) {
            $this->processFile($file);
        }
    }

    /**
     * processes a file in the tests directory and if it is a valid test file
     * then run it
     *
     * @param SplFileInfo $file
     * @return void
     */
    public function processFile(\SplFileInfo $file)
    {
        $name = $file->getFilename();

        // ignore underscore files
        if ($name[0] == '_') {
            return;
        }

        // ignore dot files
        if ($name[0] == '.') {
            return;
        }

        // ignore non test files
        if (strpos($name, 'Test.php') === false) {
            return;
        }

        $this->includeFile($name);
        $class_name = str_replace('Test.php', 'Test', $name);

        $test_case = new $class_name;
        $methods = $test_case->getTestMethods();
        foreach ($methods as $method) {
            $this->_runMethod($test_case, $method);
        }
    }

    /**
     * runs a specific test in a file
     *
     * @param TestCase $test
     * @param string $method
     * @return void
     */
    protected function _runMethod(TestCase $test, $method)
    {
        // if the method is not a test method then we should ignore it
        if (preg_match('/test[A-Z0-9_]{1}/', $method) == 0) {
            return;
        }

        $this->current(array($test, $method));

        $tracker = Tracker::getInstance();
        $tracker->incrementTestCount();

        try {
            $test->$method();
        } catch (\Exception $e) {
            $test->caughtException($e);
        }

        $test->hasFinished();

        // log the test result to command line
        if ($test->hadFailure($method)) {
            return $tracker->output('F', 'red');
        }

        if ($test->hadError($method)) {
            return $tracker->output('E', 'yellow');
        }

        return $tracker->output('.', 'green');
    }

    /**
     * gets or sets the current running test
     *
     * @param array $info
     * @return array
     */
    public function current($info = null)
    {
        if ($info !== null) {
            $this->_current = $info;
        }
        return $this->_current;
    }

    /**
     * shows the results for the tests that were run
     *
     * @return void
     */
    public function showResults()
    {
        $tracker = Tracker::getInstance();

        $tracker->outputStats();
        if (count($tracker->getFailures())) {
            $tracker->output("\nFAILURES\n", null, null, true);
            $tracker->outputFailures();
        }

        if (count($tracker->getErrors())) {
            $tracker->output("\nERRORS\n");
            $tracker->outputErrors();
        }

        $tracker->output("\n");

        if (count($tracker->getFailures())) {
            return;
        }

        if ($this->coverageDirectory()) {
            $tracker->output('Generating coverage report as html...' . "\n");
            $this->_generateCoverageHtml();
            return $tracker->output('Done' . "\n");
        }

        if ($this->showCoverage()) {
            $this->_generateCoverageCommandLine();
            return;
        }
    }

    /**
     * generates coverage report for this run as html
     *
     * @todo make this prettier with external view template
     * @return void
     */
    protected function _generateCoverageHtml()
    {
        $coverage = xdebug_get_code_coverage();
        ksort($coverage);

        foreach ($coverage as $file => $lines) {
            $this->_addToCoverageList($file, $lines);
        }

        foreach ($this->_coverage as $file => $lines) {
            $this->_writeCoverageReport($file, $lines);
        }

        $summary = '<html><head>';
        $summary .= '<style>


                    </style>';
        $summary .= '</head><body>' . "\n";
        $summary .= '<ul>' . "\n";
        $tracker = Tracker::getInstance();

        $summary .= '<li><dl><dt>File</dt><dd>All Files</dd><dt>Lines</dt><dd>' . $tracker->getTotalLines() . '</dd><dt>Covered Lines</dt><dd>' . $tracker->getCoveredLines() . '</dd><dt>Percent</dt><dd>' . $tracker->getTotalCoverage() . '%</dd></dl></li>' . "\n";

        foreach ($tracker->getCoverages() as $coverage) {
            $summary .= '<li><dl><dt>File</dt><dd><a href="' . $coverage->getPath() . '">' . $coverage->getFile() . '</a></dd><dt>Lines</dt><dd>' . $coverage->getLineCount() . '</dd><dt>Covered Lines</dt><dd>' . $coverage->getCoveredLineCount() . '</dd><dt>Percent</dt><dd>' . $coverage->getPercentCovered() . '%</dd></dl></li>' . "\n";
        }
        $summary .= '</ul>';
        file_put_contents($this->coverageDirectory() . '/index.html', $summary);
    }

    /**
     * generates coverage report for command line
     *
     * @return void
     */
    protected function _generateCoverageCommandLine()
    {
        $coverage = xdebug_get_code_coverage();
        ksort($coverage);

        foreach ($coverage as $file => $lines) {
            $this->_addToCoverageList($file, $lines);
        }

        foreach ($this->_coverage as $file => $lines) {
            $coverage = $this->_addToCoverage($file, $lines);
            $sizes[] = strlen($file);
        }

        $tracker = Tracker::getInstance();

        $file_pad = max($sizes) + 4;
        $pad = 10;

        $header = str_pad('FILE', $file_pad) . str_pad('LINES', $pad) . str_pad('COVERED', $pad) . str_pad('PERCENT', $pad). "\n";
        $tracker->output($header, 'white', 'black', true);

        $tracker->output(str_pad('All', $file_pad));
        $tracker->output(str_pad($tracker->getTotalLines(), $pad));
        $tracker->output(str_pad($tracker->getCoveredLines(), $pad));
        $total_coverage = $tracker->getTotalCoverage();
        $tracker->output(str_pad($total_coverage, $pad), $this->_getColorForCoverage($total_coverage));
        $tracker->output("\n");

        foreach ($tracker->getCoverages() as $coverage) {
            $tracker->output(str_pad($coverage->getFile(), $file_pad));
            $tracker->output(str_pad($coverage->getLineCount(), $pad));
            $tracker->output(str_pad($coverage->getCoveredLineCount(), $pad));
            $total_coverage = $coverage->getPercentCovered();
            $tracker->output(str_pad($total_coverage, $pad), $this->_getColorForCoverage($total_coverage));
            $tracker->output("\n");
        }
        $tracker->output("\n");
    }

    /**
     * determines which color to show the coverage in
     *
     * @param int $coverage
     * @return string
     */
    protected function _getColorForCoverage($coverage) {
        if ($coverage > 75) {
            return 'green';
        }

        if ($coverage > 25) {
            return 'yellow';
        }

        return 'red';
    }

    /**
     * adds a file to the coverage list
     *
     * @param string $file path to file
     * @param array $lines array from from xdebug_get_code_coverage(XDEBUG_CC_UNUSED)
     * @return void
     */
    protected function _addToCoverageList($file, $lines)
    {
        $tracker = Tracker::getInstance();

        // ignore any files in the tests directory
        if (strpos($file, $this->_directory) !== false) {
            return;
        }

        // don't track coverage on the unit test framework itself
        if (strpos($file, '/UnitTest/') !== false) {
            return;
        }

        // if there are only two lines touched that means the file was included
        // but wasn't used so let's leave it out of the coverage list
        $covered_lines = 0;
        foreach ($lines as $line => $covered) {
            if ($covered == 1) {
                ++$covered_lines;
                continue;
            }
        }

        if ($covered_lines == 2) {
            return;
        }

        // store the coverage temporarily
        $this->_coverage[$file] = $lines;
    }

    /**
     * creates a Coverage object and tracks it
     *
     * @param string $file path to file
     * @param array $lines lines from xdebug_get_code_coverage(XDEBUG_CC_UNUSED)
     * @return Coverage
     */
    protected function _addToCoverage($file, $lines)
    {
        $coverage = new Coverage($file, $lines);
        Tracker::getInstance()->addCoverage($coverage);
        return $coverage;
    }

    /**
     * writes a coverage report as html
     *
     * @param string $file path to file
     * @param array $lines lines from xdebug_get_code_coverage(XDEBUG_CC_UNUSED)
     */
    protected function _writeCoverageReport($file, $lines)
    {
        $coverage = $this->_addToCoverage($file, $lines);
        $coverage->generateReport();
        $coverage->writeToDisk($this->coverageDirectory());
    }
}
