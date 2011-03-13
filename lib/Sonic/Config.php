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
     * path to config file
     *
     * @var string
     */
    protected $_path;

    /**
     * environment name
     *
     * @var string
     */
    protected $_env;

    /**
     * config type
     *
     * @var string
     */
    protected $_type;

    /**
     * key value pair of combined sections
     *
     * @var array
     */
    protected $_combined;

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
    protected $_exception_separators = array('except', 'but skips', 'but overwrites', 'but not');

    /**
     * constructor
     *
     * @param string $path path to config file
     * @param string $environment section of config file to load
     */
    public function __construct($path, $environment, $type = self::INI)
    {
        $this->_path = $path;
        $this->_env = $environment;
        $this->_type = $type;
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
        $bits = Util::explodeAtMatch($this->_separators, $section);

        $env = trim($bits[0]);
        $this->_environments[$env] = $section;

        // if this environment inherits from another
        if (isset($bits[1])) {
            $new_bits = Util::explodeAtMatch($this->_exception_separators, $bits[1]);
            $this->_parents[$env] = trim($new_bits[0]);
            $this->_exceptions[$env] = isset($new_bits[1]) ? Util::explodeAtMatch(array(', ', ',', ' and '), trim($new_bits[1])) : array();
        }
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
    protected function _getParent(array $sections, $environment)
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
    protected function _getParents(array $sections, $environment)
    {
        $parents = array();
        while ($parent = $this->_getParent($sections, $environment)) {
            $parents[] = $parent;
            $environment = $parent;
        }
        return $parents;
    }

    /**
     * gets a config value for a key
     *
     * @param string $key
     * @param string $array_key
     * @return mixed
     */
    public function get($key, $array_key = null)
    {
        $combined = $this->getAll();
        if (!isset($combined[$key])) {
            return null;
        }

        $value = $combined[$key];
        if ($array_key !== null) {
            $value = isset($value[$array_key]) ? $value[$array_key] : null;
        }

        return $value;
    }


    /**
     * gets an array of all config values
     *
     * @return array
     */
    public function getAll()
    {
        if ($this->_combined) {
            return $this->_combined;
        }

        $path = $this->_path;
        $env = $this->_env;
        $type = $this->_type;

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
        if (!isset($map[$env])) {
            throw new Exception('environment: ' . $env . ' not found in config at : ' . $path);
        }

        $parents = $this->_getParents($sections, $env);
        $to_merge = array_reverse($parents);
        $to_merge[] = $env;

        $start = array_shift($to_merge);
        $this->_combined = $parsed_file[$start];

        foreach ($to_merge as $env) {
            $full_section = $map[$env];
            $this->_combined = Util::extendArray($this->_combined, $parsed_file[$full_section], $this->_exceptions[$env]);
        }

        return $this->_combined;
    }
}
