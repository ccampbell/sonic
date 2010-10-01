<?php
namespace Sonic;

/**
 * App singleton
 *
 * @category Sonic
 * @package App
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
     * @var Delegate
     */
    protected $_delegate;

    /**
     * @var array
     */
    protected $_paths = array();

    /**
     * @var array
     */
    protected $_controllers = array();

    /**
     * @var array
     */
    protected $_queued = array();

    /**
     * @var bool
     */
    protected $_layout_processed = false;

    /**
     * @var bool
     */
    protected $_output_started = false;

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
     * constants for settings
     */
    const MODE = 0;
    const AUTOLOAD = 1;
    const CONFIG_FILE = 2;
    const DEVS = 3;
    const FAKE_PDO = 4;
    const DISABLE_CACHE = 5;
    const TURBO = 6;
    const TURBO_PLACEHOLDER = 7;
    const DEFAULT_SCHEMA = 8;

    /**
     * @var array
     */
    protected $_settings = array(self::MODE => self::WEB,
                               self::AUTOLOAD => false,
                               self::CONFIG_FILE => 'ini',
                               self::DEVS => array('dev', 'development'),
                               self::FAKE_PDO => false,
                               self::DISABLE_CACHE => false,
                               self::TURBO => false);

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
        $path = str_replace('\\', '/', $class_name) . '.php';
        return $this->includeFile($path);
    }

    /**
     * includes a file at the given path
     *
     * @param string
     * @return void
     */
    public static function includeFile($path)
    {
        $app = self::getInstance();
        if (isset($app->_included[$path])) {
            return;
        }

        include $path;
        $app->_included[$path] = true;
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
        $cache_key =  'config_' . $path . '_' . $environment;

        // if the config is in the registry return it
        if (isset($app->_configs[$cache_key])) {
            return $app->_configs[$cache_key];
        }

        // get the config path
        if ($path === null) {
            $type = $app->getSetting(self::CONFIG_FILE);
            $path = $app->getPath('configs') . '/app.' . $type;
        }

        // if we are not dev let's try to grab it from APC
        if (!self::isDev() && !$app->getSetting(self::DISABLE_CACHE) && ($config = apc_fetch($cache_key))) {
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

        if (!$app->getSetting(self::DISABLE_CACHE)) {
            apc_store($cache_key, $config, Util::toSeconds('24 hours'));
        }

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
        return in_array($app->getEnvironment(), $app->getSetting(self::DEVS));
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

        switch ($this->getSetting(self::MODE)) {
            case self::COMMAND_LINE:
                $this->_base_path = str_replace('/libs','', get_include_path());
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
        $cache_key =  'path_' . $dir;

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
     * @param bool $json should we render json
     * @param string $id view id for if we are in turbo mode an exception is thrown
     * @return void
     */
    protected function _runController($controller_name, $action, $args = array(), $json = false, $id = null)
    {
        $this->getRequest()->addParams($args);

        $controller = $this->getController($controller_name);
        $controller->setView($action);

        $view = $controller->getView();
        $view->setAction($action);

        $view->addVars($args);

        $can_run = $json || !$this->getSetting(self::TURBO);

        if ($this->_delegate) {
            $this->_delegate->actionWasCalled($controller, $action);
        }

        // if for some reason this action has already run, let's not run it again
        if ($can_run && !$controller->hasCompleted($action)) {
            $this->_runAction($controller, $action);
        }

        // process the layout if we can
        if ($this->_processLayout($controller, $view, $args)) {
            return;
        }

        if ($this->_delegate) {
            $this->_delegate->viewStartedRendering($view, $json);
        }

        // output the view contents
        $view->output($json, $id);

        if ($this->_delegate) {
            $this->_delegate->viewFinishedRendering($view, $json);
        }
    }

    /**
     * processes the layout if it needs to be processed
     *
     * @param Controller $controller
     * @param View $view
     * @param array $args
     * @return bool
     */
    protected function _processLayout(Controller $controller, View $view, $args)
    {
        // if the layout was already processed ignore this call
        if ($this->_layout_processed) {
            return false;
        }

        // if the controller doesn't have a layout ignore this call
        if (!$controller->hasLayout()) {
            return false;
        }

        // if this is not the first controller and not an exception, ignore
        if (count($this->_controllers) != 1 && !isset($args['exception'])) {
            return false;
        }

        // process the layout!
        $this->_layout_processed = true;
        $layout = $controller->getLayout();
        $layout->topView($view);

        if ($this->_delegate) {
            $this->_delegate->layoutStartedRendering($layout);
        }

        $layout->output();

        if ($this->_delegate) {
            $this->_delegate->layoutFinishedRendering($layout);
        }

        return true;
    }

    /**
     * runs a specific action in a controller
     *
     * @param Controller $controller
     * @param string $action
     * @return void
     */
    protected function _runAction(Controller $controller, $action)
    {
        if ($this->_delegate) {
            $this->_delegate->actionStartedRunning($controller, $action);
        }

        $controller->$action();
        $controller->actionComplete($action);

        if ($this->_delegate) {
            $this->_delegate->actionFinishedRunning($controller, $action);
        }
    }

    /**
     * public access to run a controller (handles exceptions)
     *
     * @param string $controller_name controller to use
     * @param string $action method within controller to execute
     * @param array $args arguments to be added to the Request object and view
     * @param bool $json should we render json?
     * @param string $controller_name
     */
    public function runController($controller_name, $action, $args = array(), $json = false)
    {
        try {
            $this->_runController($controller_name, $action, $args, $json);
        } catch (\Exception $e) {
            $this->_handleException($e, $controller_name, $action);
            return;
        }
    }

    /**
     * tells the application that output has started
     *
     * @return void
     */
    public function outputStarted($started = null)
    {
        if ($started) {
            $this->_output_started = true;
        }
        return $this->_output_started;
    }

    /**
     * queues up a view for later processing
     *
     * only happens in turbo mode
     *
     * @param string
     * @param string
     * @return void
     */
    public function queueView($controller, $name)
    {
        $this->_queued[] = array($controller, $name);
    }

    /**
     * processes queued up views for turbo mode
     *
     * @return void
     */
    public function processViewQueue()
    {
        if (!$this->getSetting(self::TURBO)) {
            return;
        }

        while (count($this->_queued)) {
            foreach ($this->_queued as $key => $queue) {
                $this->runController($queue[0], $queue[1], array(), true);
                unset($this->_queued[$key]);
            }
        }
    }

    /**
     * determines if we should turn off turbo mode
     *
     * @return bool
     */
    protected function _robotnikWins()
    {
        if ($this->getRequest()->isAjax()) {
            return true;
        }

        if (isset($_COOKIE['noturbo']) || isset($_COOKIE['bot'])) {
            return true;
        }

        if (isset($_GET['noturbo'])) {
            setcookie('noturbo', true, time() + 86400);
            return true;
        }

        if (strpos($_SERVER['HTTP_USER_AGENT'], 'Googlebot') !== false) {
            setcookie('bot', true, time() + 86400);
            return true;
        }

        return false;
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
        if ($this->_delegate) {
            $this->_delegate->appCaughtException($e, $controller, $action);
        }

        if (!$this->outputStarted()) {
            header('HTTP/1.1 500 Internal Server Error');
            if ($e instanceof \Sonic\Exception) {
                header($e->getHttpCode());
            }
        }

        $json = false;
        $id = null;

        if ($this->getSetting(self::TURBO) && $this->_layout_processed) {
            $json = true;
            $id = View::generateId($controller, $action);
        }

        // if this is a not found exception then these calls will end up rethrowing the exception
        if ($e->getCode() !== \Sonic\Exception::NOT_FOUND) {
            $request = $this->getRequest();
            $action = $request->getControllerName() . '::' . $request->getAction();
        }

        $args = array(
            'exception' => $e,
            'top_level_exception' => !$this->outputStarted(),
            'from_controller' => $controller,
            'from_action' => $action
        );

        return $this->_runController('main', 'error', $args, $json, $id);
    }

    /**
     * sets a delegate class to receive events as the application runs
     *
     * @param string $delegate name of delegate class
     * @return \Sonic\App
     */
    public function setDelegate($delegate)
    {
        $this->includeFile('Sonic/App/Delegate.php');
        $this->autoloader($delegate);

        $this->_delegate = new $delegate;

        if (!$this->_delegate instanceof \Sonic\App\Delegate) {
            throw new \Exception('app delegate of class ' . get_class($delegate) . ' must be instance of \Sonic\App\Delegate');
        }

        $this->_delegate->setApp($this);
        return $this;
    }

    /**
     * pushes over the first domino
     *
     * @return void
     */
    public function start($mode = self::WEB)
    {
        if ($this->_delegate) {
            $this->_delegate->appStartedLoading($mode);
        }

        $this->addSetting(self::MODE, $mode);

        // this could use App::includeFile() but it is faster to duplicate
        // that logic here
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

        if ($this->getSetting(self::AUTOLOAD)) {
            $this->autoload();
        }

        // if we are calling this app from command line then all we want to do
        // is load the core application files
        if ($mode != self::WEB) {
            return;
        }

        if ($this->getSetting(self::TURBO) && $this->_robotnikWins()) {
            $this->addSetting(self::TURBO, false);
        }

        if ($this->_delegate) {
            $this->_delegate->appFinishedLoading();
        }

        // try to get the controller and action
        // if an exception is thrown that means the page requested does not exist
        try {
            $controller = $this->getRequest()->getControllerName();
            $action = $this->getRequest()->getAction();
        } catch (\Exception $e) {
            return $this->_handleException($e);
        }

        if ($this->_delegate) {
            $this->_delegate->appStartedRunning();
        }

        $this->runController($controller, $action);

        if ($this->_delegate) {
            $this->_delegate->appFinishedRunning();
        }
    }
}
