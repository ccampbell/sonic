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
     * @var bool
     */
    protected $_has_config = false;

    /**
     * @var array
     */
    protected $_config_defaults = array();

    /**
     * @var array
     */
    protected $_dependencies = array();

    /**
     * @var array
     */
    protected $_routes = array();

    /**
     * should we load the files in libs when the extension is first loaded in the app
     *
     * @var bool
     */
    protected $_load_libs = false;

    /**
     * @var string
     */
    protected $_instructions = '';

    /**
     * gets dependencies
     *
     * @return array
     */
    public function getDependencies()
    {
        return array_map('strtolower', $this->_dependencies);
    }

    /**
     * should we install a config file?
     *
     * @return bool
     */
    public function hasConfig()
    {
        return $this->_has_config;
    }

    /**
     * get default config values
     *
     * @return array
     */
    public function getConfigDefaults()
    {
        return $this->_config_defaults;
    }

    /**
     * gets a list of routes to be installed with this extension
     *
     * @return array
     */
    public function getRoutes()
    {
        return $this->_routes;
    }

    /**
     * gets extra installation instructions
     *
     * @return string
     */
    public final function getInstructions()
    {
        $instructions = '';
        if ($this->_instructions) {
            $instructions .= "\n" . '----------------------------' . "\n";
            $instructions .= " INSTRUCTIONS" . "\n";
            $instructions .= '----------------------------' . "\n";
            $instructions .= $this->_instructions . "\n";
        }
        return $instructions;
    }

    /**
     * should we load the files in the libs directory in the app
     *
     * @return bool
     */
    public function loadLibs()
    {
        return $this->_load_libs;
    }
}
