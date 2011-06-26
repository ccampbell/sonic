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
     * does this extension have a config file associated with it?
     *
     * if so it will be created in configs/extension.{extension_name}.ini
     *
     * @var bool
     */
    protected $_has_config = false;

    /**
     * optionally you can set some default configuration values
     * to give your users an idea of what can be done
     *
     * @var array
     */
    protected $_config_defaults = array();

    /**
     * this is an array of dependencies for this extension
     *
     * @var array
     */
    protected $_dependencies = array();

    /**
     * additional routes that will be added to your configs/routes.php file
     * when you install this extension
     *
     * @var array
     */
    protected $_routes = array();

    /**
     * an array of files to keep on upgrade
     * this is so if your extension installs a file and you upgrade the extension
     * that file will not be overwritten
     *
     * @var array
     */
    protected $_keep_on_upgrade = array();

    /**
     * should we load the files in libs when the extension is first loaded in the app
     *
     * @var bool
     */
    protected $_load_libs = false;

    /**
     * additional instructions to be displayed in command line after the extension
     * has been successfully installed
     *
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
     * gets files to keep on upgrade
     *
     * @return array
     */
    public function keepOnUpgrade()
    {
        return $this->_keep_on_upgrade;
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
