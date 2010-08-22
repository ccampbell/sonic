<?php
namespace Sonic;

/**
 * App singleton
 *
 * @package Sonic
 * @subpackage App
 * @author Craig Campbell
 */
class App
{
    /**
     * @var string
     */
    const WEB = 'www';

    /**
     * @var string
     */
    const COMMAND_LINE = 'cli';

    /**
     * @var App
     */
    protected static $_instance;

    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var array
     */
    protected $_paths = array();

    /**
     * @var array
     */
    protected $_controllers = array();

    /**
     * @var bool
     */
    protected $_layout_processed = false;

    /**
     * @var array
     */
    protected $_configs = array();

    /**
     * @var array
     */
    protected static $_included = array();

    /**
     * @var string
     */
    protected $_base_path;

    /**
     * @var string
     */
    protected $_environment;

    /**
     * @var array
     */
    protected $_settings = array('mode' => self::WEB,
                               'autoload' => false,
                               'config_file' => 'php',
                               'devs' => array('dev', 'development'));

    /**
     * constructor
     *
     * @return void
     */
    private function __construct() {}

    /**
     * gets instance of App class
     *
     * @return App
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new App();
        }
        return self::$_instance;
    }

    /**
     * handles autoloading
     *
     * @param string $class_name
     * @return void
     */
    public function autoloader($class_name)
    {
        include str_replace('\\', '/', $class_name) . '.php';
    }

    /**
     * includes a file at the given path
     *
     * @param string
     * @return void
     */
    public function includeFile($path)
    {
        if (isset($this->_included[$path])) {
            return;
        }

        include $path;
        $this->_included[$path] = true;
    }

    /**
     * initializes autoloader
     *
     * @return void
     */
    public function autoload()
    {
        spl_autoload_register(array($this, 'autoloader'));
    }

    /**
     * enables autoloading
     *
     * @return void
     */
    public function enableAutoload()
    {
        $this->addSetting('autoload', true);
    }

    /**
     * sets a setting
     *
     * @param string $key
     * @param mixed $value
     */
    public function addSetting($key, $value)
    {
        $this->_settings[$key] = $value;
    }

    /**
     * gets a setting
     *
     * @param string $name
     * @return mixed
     */
    public function getSetting($name)
    {
        if (!isset($this->_settings[$name])) {
            return null;
        }

        return $this->_settings[$name];
    }

    /**
     * returns the config
     *
     * first tries to grab it from APC then tries to grab it from instance cache
     * if neither of those succeed then it will instantiate the config object
     * and add it to instance cache and/or APC
     *
     * @param string $path path to config path
     * @param string $type (php || ini)
     * @return Config
     */
    public static function getConfig($path = null)
    {
        $app = self::getInstance();
        $environment = $app->getEnvironment();

        // cache key
        $cache_key = __METHOD__ . '_' . $path . '_' . $environment;

        // if the config is in the registry return it
        if (isset($app->_configs[$cache_key])) {
            return $app->_configs[$cache_key];
        }

        // get the config path
        if ($path === null) {
            $type = $app->getSetting('config_file');
            $path = $app->getPath('configs') . '/app.' . $type;
        }

        // if we are not dev let's try to grab it from APC
        if (!self::isDev() && ($config = apc_fetch($cache_key))) {
            $app->_configs[$cache_key] = $config;
            return $config;
        }

        // include the class
        $app->includeFile('Sonic/Config.php');
        $app->includeFile('Sonic/Util.php');

        // if we have gotten here then that means the config exists so we
        // now need to get the environment name and load the config
        $config = new Config($path, $environment, $type);
        $app->_configs[$cache_key] = $config;
        apc_store($cache_key, $config, Util::toSeconds('24 hours'));

        return $config;
    }

    /**
     * gets memcache
     *
     * @return Sonic\Cache\Memcache
     */
    public static function getMemcache($pool = 'default')
    {
        return Cache\Factory::getMemcache($pool);
    }

    /**
     * gets memcached
     *
     * @return Sonic\Cache\Memcached
     */
    // public static function getMemcached($pool = 'default')
    // {
        // return Cache\Factory::getMemcached($pool);
    // }

    /**
     * is this dev mode?
     *
     * @return bool
     */
    public static function isDev()
    {
        $app = self::getInstance();
        return in_array($app->getEnvironment(), $app->getSetting('devs'));
    }

    /**
     * gets apache/unix environment name
     *
     * @return string
     */
    public function getEnvironment()
    {
        if ($this->_environment !== null) {
            return $this->_environment;
        }

        if ($environment = getenv('ENVIRONMENT')) {
            $this->_environment = $environment;
            return $environment;
        }

        throw new Exception('ENVIRONMENT variable is not set! check your apache config');
    }

    /**
     * gets all controller::action() combinations that have been executed on this page load
     *
     * @return array
     */
    public function getAllActions()
    {
        $actions = array();
        foreach ($this->_controllers as $controller) {
            foreach ($controller->getActionsCompleted() as $action) {
                $actions[] = $controller->name() . '::' . $action;
            }
        }
        return $actions;
    }

    /**
     * gets the request object
     *
     * @return Request
     */
    public function getRequest()
    {
        if (!$this->_request) {
            $this->_request = new Request();
        }
        return $this->_request;
    }

    /**
     * gets base path of the app
     *
     * @return string
     */
    public function getBasePath()
    {
        if ($this->_base_path) {
            return $this->_base_path;
        }

        switch ($this->getSetting('mode')) {
            case self::COMMAND_LINE:
                $this->_base_path = str_replace('/lib','', get_include_path());
                break;
            default:
                $document_root = $this->getRequest()->getServer('DOCUMENT_ROOT');
                $this->_base_path = str_replace('/public_html', '', $document_root);
        }

        return $this->_base_path;
    }

    /**
     * gets the absolute path to a directory
     *
     * @param string $dir (views || controllers || lib) etc
     * @return string
     */
    public function getPath($dir = null)
    {
        $cache_key = __METHOD__ . '_' . $dir;

        if (isset($this->_paths[$cache_key])) {
            return $this->_paths[$cache_key];
        }

        $base_path = $this->getBasePath();

        if ($dir !== null) {
            $base_path .= '/' . $dir;
        }

        $this->_paths[$cache_key] = $base_path;
        return $this->_paths[$cache_key];
    }

    /**
     * globally disables layout
     *
     * @return void
     */
    public function disableLayout()
    {
        $this->_layout_processed = true;
    }

    /**
     * gets a controller by name
     *
     * @param string $name
     * @return Controller
     */
    public function getController($name)
    {
        if (isset($this->_controllers[$name])) {
            return $this->_controllers[$name];
        }

        include $this->getPath('controllers') . '/' . $name . '.php';
        $class_name = '\Controllers\\' . $name;
        $this->_controllers[$name] = new $class_name();
        $this->_controllers[$name]->name($name);
        $this->_controllers[$name]->request($this->getRequest());

        return $this->_controllers[$name];
    }

    /**
     * runs a controller and action combination
     *
     * @param string $controller_name controller to use
     * @param string $action method within controller to execute
     * @param array $args arguments to be added to the Request object and view
     * @return void
     */
    protected function _runController($controller_name, $action, $args = array())
    {
        $this->getRequest()->addParams($args);

        $controller = $this->getController($controller_name);
        $controller->setView($action);

        $view = $controller->getView();
        $view->addVars($args);

        $run_action = false;

        // if we have already initialized the controller let's not do it again
        if (!$controller->hasCompleted('init')) {
            $run_action = true;

            // incase the init triggers an exception we don't want to run it again
            $controller->actionComplete('init');
            $controller->init();
        }

        // if for some reason this action has already run, let's not do it again
        if ($run_action || !$controller->hasCompleted($action)) {
            $controller->$action();
            $controller->actionComplete($action);
        }

        // if this is the first controller and no layout has been processed and it has a layout start with that
        if (!$this->_layout_processed && $controller->hasLayout() && count($this->_controllers) === 1) {
            $this->_layout_processed = true;
            $layout = $controller->getLayout();
            $layout->topView($view);
            return $layout->output();
        }

        // output the view contents
        $view->output();
    }

    /**
     * public access to run a controller (handles exceptions)
     *
     * @param string $controller_name controller to use
     * @param string $action method within controller to execute
     * @param array $args arguments to be added to the Request object and view
     * @param string $controller_name
     */
    public function runController($controller_name, $action, $args = array())
    {
        try {
            $this->_runController($controller_name, $action, $args);
        } catch (\Exception $e) {
            $this->_handleException($e, $controller_name, $action);
        }
    }

    /**
     * handles an exception when loading a page
     *
     * @param Exception $e
     * @param string $controller name of controller
     * @param string $action name of action
     * @return void
     */
    protected function _handleException(\Exception $e, $controller = null, $action = null)
    {
        $this->_runController('main', 'error', array('exception' => $e, 'from_controller' => $controller, 'from_action' => $action));
    }

    /**
     * pushes over the first domino
     *
     * @return void
     */
    public function start($mode = self::WEB)
    {
        $this->addSetting('mode', $mode);

        include 'Sonic/Exception.php';
        $this->_included['Sonic/Exception.php'] = true;
        include 'Sonic/Request.php';
        $this->_included['Sonic/Request.php'] = true;
        include 'Sonic/Router.php';
        $this->_included['Sonic/Router.php'] = true;
        include 'Sonic/Controller.php';
        $this->_included['Sonic/Controller.php'] = true;
        include 'Sonic/View.php';
        $this->_included['Sonic/View.php'] = true;
        include 'Sonic/Layout.php';
        $this->_included['Sonic/Layout.php'] = true;

        if ($this->getSetting('autoload')) {
            $this->autoload();
        }

        if ($mode != self::WEB) {
            return;
        }

        // try to get the controller and action
        // if an exception is thrown that means the page requested does not exist
        try {
            $controller = $this->getRequest()->getControllerName();
            $action = $this->getRequest()->getAction();
        } catch (\Exception $e) {
            return $this->_handleException($e);
        }

        $this->runController($controller, $action);
    }
}
