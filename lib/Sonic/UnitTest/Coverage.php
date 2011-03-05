<?php
namespace Sonic\UnitTest;

/**
 * Coverage
 *
 * @category Sonic
 * @package UnitTest
 * @author Craig Campbell
 */
class Coverage
{
    /**
     * array of all lines in the file
     *
     * @var array
     */
    protected $_lines;

    /**
     * path to file
     *
     * @var string
     */
    protected $_file;

    /**
     * lines that were covered by unit tests
     *
     * @var array
     */
    protected $_covered_lines = array();

    /**
     * lines that could be covered by unit tests but were not
     *
     * @var array
     */
    protected $_uncovered_lines = array();

    /**
     * keeps track of if this file was processed
     *
     * @var bool
     */
    protected $_processed = false;

    /**
     * html report to write to disk
     *
     * @var string
     */
    protected $_html_report;

    /**
     * keeps track of if the coverage directory was created to make sure we
     * only delete it once per run
     *
     * @var bool
     */
    protected static $_directory_created = false;

    /**
     * constructor
     *
     * @param string $file path to file
     * @param array $lines report for this file from xdebug_get_code_coverage(XDEBUG_CC_UNUSED)
     * @return void
     */
    public function __construct($file, $lines)
    {
        $this->_file = $file;

        // this is an array of all lines that CAN run
        // xdebug ignores lines like comments and class variables
        foreach ($lines as $line => $covered) {
            if ($covered == 1) {
                $this->_covered_lines[] = $line;
                continue;
            }
            $this->_uncovered_lines[] = $line;
        }
    }

    /**
     * reads the file from disk to get the lines and then cleans up which
     * the lines that can be processed
     *
     * @return void
     */
    protected function _process()
    {
        if ($this->_processed) {
            return;
        }

        $contents = file_get_contents($this->getFile());
        $this->_lines = explode("\n", $contents);
        $this->_processed = true;
        $this->_cleanUpLineCoverage();
    }

    /**
     * cleans up xdebug line coverage reporting
     *
     * there is a bug in xdebug where any } line that follows a return or a
     * thrown exception will not get reported as covered since the code
     * technically is never run.
     *
     * this function finds those lines and marks them as covered
     *
     * @return void
     */
    protected function _cleanUpLineCoverage()
    {
        foreach ($this->_lines as $key => $line) {
            $line_number = $key + 1;

            if (trim($line) == '}' && preg_match('/^(throw|return|break)/', trim($this->_lines[$key - 1]))) {
                $key = array_search($line_number, $this->_uncovered_lines);
                if ($key !== false) {
                    unset($this->_uncovered_lines[$key]);
                }
            }
        }
    }

    /**
     * generates an html report for this file
     *
     * @todo use a template file and move the markup out of php
     * @return void
     */
    public function generateReport()
    {
        $this->_process();
        $output = '<html><head>';
        $output .= '<style>

                body, html {
                    font-family: monospace;
                    font-size: 1em;
                    white-space: pre-wrap;
                }
                ul, li {
                    line-height: .25em;
                    margin: 0px;
                    padding: 0px;
                }
                ul, li {
                    list-style: none;
                }
                li {
                    padding: 6px 0px 8px 0px;
                }
                li.alt {
                    background: #ddd;
                }
                li.covered {
                    background: #00DD02;
                }
                li.not_covered {
                    background: #FF6A78;
                }
            </style>';

        $output .= '</head><body><h1>Coverage For ' . $this->getFile() . '</h1>' . "\n";
        $output .= '<h2>' . $this->getPercentCovered() . '%</h2>' . "\n";
        $output .= '<ul>' . "\n";

        foreach ($this->_lines as $key => $line) {
            $line_number = $key + 1;
            $output .= '<li class="';

            $alt = $key % 2 == 0;
            if ($alt) {
                $output .= 'alt';
            }

            if (in_array($line_number, $this->getCoveredLines())) {
                $output .=  ($alt ? ' ' : '') . 'covered';
            }

            if (in_array($line_number, $this->getUncoveredLines())) {
                $output .=  ($alt ? ' ' : '') . 'not_covered';
            }

            $output .= '">' . htmlentities($line, ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
        }
        $output .= '</ul></body>' . "\n";
        $this->_html_report = $output;
    }

    /**
     * gets the file name this coverage info is for
     *
     * @return string
     */
    public function getFile()
    {
        return $this->_file;
    }

    /**
     * writes the html report to disk in the provided directory
     *
     * @param string $dir
     * @return void
     */
    public function writeToDisk($dir)
    {
        self::createDirectory($dir);
        file_put_contents($dir . '/' . $this->getPath(), $this->_html_report);
    }

    /**
     * creates the directory if it has not already been created
     *
     * @param string $dir
     * @return void
     */
    public static function createDirectory($dir)
    {
        if (self::$_directory_created) {
            return;
        }

        if (is_dir($dir)) {
            shell_exec('rm -r ' . $dir);
        }
        mkdir($dir);
        self::$_directory_created = true;
    }

    /**
     * gets the path to where the html file should be saved
     *
     * @return string
     */
    public function getPath()
    {
        $path = str_replace('/', '_', $this->getFile());
        $path = str_replace('.php', '.html', $path);
        $path = substr($path, 1);
        return $path;
    }

    /**
     * gets all the lines in the file
     *
     * @return array
     */
    public function getLines()
    {
        $this->_process();
        return $this->_lines;
    }

    /**
     * gets all covered line numbers
     *
     * @return array
     */
    public function getCoveredLines()
    {
        $this->_process();
        return $this->_covered_lines;
    }

    /**
     * gets all line numbers that have not been covered
     *
     * @return array
     */
    public function getUncoveredLines()
    {
        $this->_process();
        return $this->_uncovered_lines;
    }

    /**
     * gets covered line count
     *
     * @return int
     */
    public function getCoveredLineCount()
    {
        $this->_process();
        return count($this->_covered_lines);
    }

    /**
     * gets uncovered line count
     *
     * @return int
     */
    public function getUncoveredLineCount()
    {
        $this->_process();
        return count($this->_uncovered_lines);
    }

    /**
     * gets line count for all lines that can be covered
     *
     * @return int
     */
    public function getLineCount()
    {
        $this->_process();
        return $this->getCoveredLineCount() + $this->getUncoveredLineCount();
    }

    /**
     * gets the percentage of all lines covered in this file
     *
     * @return float
     */
    public function getPercentCovered()
    {
        $this->_process();
        return round(($this->getCoveredLineCount() / $this->getLineCount()) * 100, 2);
    }
}
