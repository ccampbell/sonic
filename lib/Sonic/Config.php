<?php
namespace Sonic;

/**
 * Config
 *
 * @category Sonic
 * @package Config
 * @author Craig Campbell
 * @version 1.0 beta
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
     * @var
     */
    protected $_environments = array();

    /**
     * mapping of environment to parent environment
     *
     * @var
     */
    protected $_parents = array();

    /**
     * constructor
     *
     * @param string $path path to config file
     * @param string $environment section of config file to load
     */
    public function __construct($path, $environment, $type = self::PHP)
    {
        // parse the file
        switch ($type) {
            case self::INI:
                $parsed_file = parse_ini_file($path, true);
                break;
            default:
                include $path;
                $parsed_file = $config;
                break;
        }

        // find all the environments
        $sections = array_keys($parsed_file);

        // make sure environment exists
        $map = $this->_getEnvironmentMap($sections);
        if (!isset($map[$environment])) {
            throw new Exception('environment : ' . $environment .
                ' not found in config at : ' . $path);
        }

        $parents = $this->getParents($sections, $environment);
        $to_merge = array_reverse($parents);
        $to_merge[] = $environment;

        $start = array_shift($to_merge);
        $this->_combined = $parsed_file[$start];

        foreach ($to_merge as $environment) {
            $full_section = $map[$environment];
            $this->_combined = array_merge($this->_combined, $parsed_file[$full_section]);
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
        $bits = explode(':', $section);
        $this->_environments[trim($bits[0])] = $section;
        if (isset($bits[1])) {
            $this->_parents[trim($bits[0])] = trim($bits[1]);
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
