<?php
namespace Sonic;

/**
 * Config
 *
 * @category Sonic
 * @package Config
 * @author Craig Campbell
 */
class Config
{
    /**
     * @var string
     */
    const PHP = 'array';

    /**
     * @var string
     */
    const INI = 'ini';

    /**
     * key value pair of combined sections
     *
     * @var array
     */
    protected $_combined;

    /**
     * array of Yoshi_Config_Section objects
     *
     * @var array
     */
    protected $_sections = array();

    /**
     * array of valid environments
     *
     * @var array
     */
    protected $_environments = array();

    /**
     * mapping of environment to parent environment
     *
     * @var array
     */
    protected $_parents = array();

    /**
     * list of variables to exclude from inheritence
     *
     * @var array
     */
    protected $_exceptions = array();

    /**
     * list of inheritance separators
     *
     * for example:
     *
     * [production : global]
     * [production extends global]
     * [production inherits from global]
     *
     * @var array
     */
    protected $_separators = array(':', 'extends', 'inherits from', 'inherits');

    /**
     * separator we are using
     *
     * @var string
     */
    protected $_separator;

    /**
     * list of inheritance exceptions
     *
     * for example:
     *
     * [production extends global but overwrites urls]
     * [production : global - urls]
     * [production extends global but not urls,cache,db]
     *
     * @var array
     */
    protected $_exception_separators = array('-', 'except', 'but skips', 'but overwrites', 'but not');

    /**
     * exception we are using
     *
     * @var string
     */
    protected $_exception_separator;

    /**
     * constructor
     *
     * @param string $path path to config file
     * @param string $environment section of config file to load
     */
    public function __construct($path, $environment, $type = self::INI)
    {
        if (!file_exists($path)) {
            throw new Exception('configuration file does not exist at ' . $path);
        }

        // parse the file
        switch ($type) {
            case self::PHP:
                include $path;
                $parsed_file = $config;
                break;
            default:
                $parsed_file = parse_ini_file($path, true);
                break;
        }

        // find all the environments
        $sections = array_keys($parsed_file);

        // make sure environment exists
        $map = $this->_getEnvironmentMap($sections);
        if (!isset($map[$environment])) {
            throw new Exception('environment: ' . $environment . ' not found in config at : ' . $path);
        }

        $parents = $this->getParents($sections, $environment);
        $to_merge = array_reverse($parents);
        $to_merge[] = $environment;

        $start = array_shift($to_merge);
        $this->_combined = $parsed_file[$start];

        foreach ($to_merge as $environment) {
            $full_section = $map[$environment];
            $this->_combined = Util::extendArray($this->_combined, $parsed_file[$full_section], $this->_exceptions[$environment]);
        }
    }

    /**
     * internal function to process environments from ini sections
     *
     * @param array $sections
     * @return void
     */
    protected function _processEnvironments(array $sections)
    {
        foreach ($sections as $section) {
            $this->_addSectionToMap($section);
        }
    }

    /**
     * adds a section to the internal maps
     *
     * @param string $section
     * @return void
     */
    protected function _addSectionToMap($section)
    {
        // figur out what separator to use
        $separator = $this->_getSeparator($section);
        $bits = explode($separator, $section);

        $env = trim($bits[0]);
        $this->_environments[$env] = $section;

        // if this environment inherits from another
        if (isset($bits[1])) {
            $separator = $this->_getExceptionSeparator($bits[1]);
            $new_bits = explode($separator, $bits[1]);
            $this->_parents[$env] = trim($new_bits[0]);
            $this->_exceptions[$env] = isset($new_bits[1]) ? explode(',', trim($new_bits[1])) : array();
        }
    }

    /**
     * figures out what separator to use for inheritence
     *
     * @param string $section
     * @return string
     */
    protected function _getSeparator($section)
    {
        if ($this->_separator) {
            return $this->_separator;
        }

        foreach ($this->_separators as $separator) {
            if (strpos($section, $separator) > 0) {
                $this->_separator = $separator;
                return $this->_separator;
            }
        }

        return $this->_separators[0];
    }

    /**
     * figures out what separator to use for keys to skip
     *
     * @param string $env
     * @return string
     */
    protected function _getExceptionSeparator($env)
    {
        if ($this->_exception_separator) {
            return $this->_exception_separator;
        }

        foreach ($this->_exception_separators as $separator) {
            if (strpos($env, $separator) > 0) {
                $this->_exception_separator = $separator;
                return $this->_exception_separator;
            }
        }

        return $this->_exception_separators[0];
    }

    /**
     * internal mapping of environment names to full environment names
     *
     * @param array $sections
     * @return array
     */
    protected function _getEnvironmentMap(array $sections)
    {
        if (empty($this->_environments)) {
            $this->_processEnvironments($sections);
        }
        return $this->_environments;
    }

    /**
     * internal mapping of environment names to parent names
     *
     * @param array $sections
     * @return array
     */
    protected function _getParentMap(array $sections)
    {
        if (empty($this->_parents)) {
            $this->_processEnvironments($sections);
        }
        return $this->_parents;
    }

    /**
     * gets the direct parent for this section
     *
     * @param array $sections
     * @param string $environment
     * @return string
     */
    public function getParent(array $sections, $environment)
    {
        $map = $this->_getParentMap($sections);
        if (isset($map[$environment])) {
            return $map[$environment];
        }
        return null;
    }

    /**
     * gets all the parents for a particular environment
     *
     * @param array $sections
     * @param string $environment
     * @return array
     */
    public function getParents(array $sections, $environment)
    {
        $parents = array();
        while ($parent = $this->getParent($sections, $environment)) {
            $parents[] = $parent;
            $environment = $parent;
        }
        return $parents;
    }

    /**
     * gets an array of all config values
     *
     * @return array
     */
    public function getAll()
    {
        return $this->_combined;
    }

    /**
     * gets a config value for a key
     *
     * @param string $key
     * @return mixed
     */
    public function get($key, $array_key = null)
    {
        if (!isset($this->_combined[$key]))
            return null;

        $value = $this->_combined[$key];
        if ($array_key !== null) {
            $value = $value[$array_key];
        }

        return $value;
    }
}
