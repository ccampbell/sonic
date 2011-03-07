<?php
namespace Sonic;

/**
 * App singleton
 *
 * @category Sonic
 * @package App
 * @author Craig Campbell
 */
final class App
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
     * @var float
     */
    const VERSION = '1.1';

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
     * @var array
     */
    protected $_configs = array();

    /**
     * @var array
     */
    protected $_included = array();

    /**
     * @var string
     */
    protected $_base_path;

    /**
     * constants for settings
     */
    const MODE = 0;
    const ENVIRONMENT = 1;
    const AUTOLOAD = 2;
    const CONFIG_FILE = 3;
    const DEVS = 4;
    const DB_DRIVER = 5;
    const PDO = 6;
    const MYSQL = 7;
    const MYSQLI = 8;
    const DISABLE_APC = 9;
    const TURBO = 10;
    const TURBO_PLACEHOLDER = 11;
    const DEFAULT_SCHEMA = 12;
    const EXTENSION_DATA = 13;
    const EXTENSIONS_LOADED = 14;

    /**
     * @var array
     */
    protected $_settings = array(
        self::MODE => self::WEB,
        self::AUTOLOAD => false,
        self::CONFIG_FILE => 'ini',
        self::DEVS => array('dev', 'development'),
        self::DB_DRIVER => self::PDO,
        self::DISABLE_APC => false,
        self::TURBO => false,
        self::EXTENSIONS_LOADED => array()
    );

    /**
     * constructor
     *
     * @return void
     */
    private function __construct() {}

    /**
     * magic call for methods added at runtime
     *
     * @param string $name
     * @param array $args
     */
    public function __call($name, $args)
    {
        return $this->callIfExists($name, $args, __CLASS__, get_class($this));
    }

    /**
     * magic static call for methods added at run time
     *
     * @param string $name
     * @param array $args
     */
    public static function __callStatic($name, $args)
    {
        return self::getInstance()->callIfExists($name, $args, __CLASS__, get_called_class(), true);
    }

    /**
     * calls method if it exists
     *
     * @param string $name
     * @param array $args
     * @param string $class
     * @param instance $class_name
     */
    public function callIfExists($name, $args, $class, $class_name, $static = false)
    {
        if (count($this->getSetting(self::EXTENSIONS_LOADED)) == 0) {
            return trigger_error('Call to undefined method ' . $class_name . '::' . $name . '()', E_USER_ERROR);
        }
        $this->includeFile('Sonic/Extension/Transformation.php');
        $method = $static ? 'callStatic' : 'call';
        return Extension\Transformation::$method($name, $args, $class, $class_name);
    }

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
    public function includeFile($path)
    {
        if (isset($this->_included[$path])) {
            return false;
        }

        include $path;
        $this->_included[$path] = true;
        return true;
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

        $type = $app->getSetting(self::CONFIG_FILE);

        // get the config path
        if ($path === null) {
            $path = $app->getPath('configs') . '/app.' . $type;
        }

        // cache key
        $cache_key =  'config_' . $path . '_' . $environment;

        // if the config is in instance cache return it
        if (isset($app->_configs[$cache_key])) {
            return $app->_configs[$cache_key];
        }

        // we need to load the config object before it fetches it from APC
        $app->includeFile('Sonic/Config.php');

        // if we are not dev let's try to grab it from APC
        if (!self::isDev() && !$app->getSetting(self::DISABLE_APC) && ($config = apc_fetch($cache_key))) {
            $app->_configs[$cache_key] = $config;
            return $config;
        }

        // include the class
        $app->includeFile('Sonic/Util.php');

        // if we have gotten here then that means the config exists so we
        // now need to get the environment name and load the config
        $config = new Config($path, $environment, $type);
        $app->_configs[$cache_key] = $config;

        if (!self::isDev() && !$app->getSetting(self::DISABLE_APC)) {
            apc_store($cache_key, $config, Util::toSeconds('24 hours'));
        }

        return $config;
    }

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
        if ($env = $this->getSetting(self::ENVIRONMENT)) {
            return $env;
        }

        if ($env = getenv('ENVIRONMENT')) {
            $this->addSetting(self::ENVIRONMENT, $env);
            return $env;
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

        if ($this->getSetting(self::MODE) == self::COMMAND_LINE) {
            $this->_base_path = str_replace(array('/libs', '/lib'), '', get_include_path());
            return $this->_base_path;
        }

        $this->_base_path = str_replace('/public_html', '', $this->getRequest()->getServer('DOCUMENT_ROOT'));
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
     * overrides base path
     *
     * @param string $dir
     * @return void
     */
    public function setBasePath($path)
    {
        $this->_base_path = $path;
    }

    /**
     * overrides a default path
     *
     * @param string $dir
     * @param string $path
     * @return void
     */
    public function setPath($dir, $path)
    {
        $this->_paths['path_' . $dir] = $path;
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
        $name = strtolower($name);

        // controller has not been instantiated yet
        if (!isset($this->_controllers[$name])) {
            include $this->getPath('controllers') . '/' . $name . '.php';
            $class_name = '\Controllers\\' . $name;
            $this->_controllers[$name] = new $class_name();
            $this->_controllers[$name]->name($name);
            $this->_controllers[$name]->request($this->getRequest());
        }

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
        $controller->setView($action, false);

        $view = $controller->getView();
        $view->setAction($action);
        $view->addVars($args);

        // if we are requesting JSON that means this is being processed from the turbo queue
        // if we are not in turbo mode then we run the action normally
        $can_run = $json || !$this->getSetting(self::TURBO);

        if ($this->_delegate) {
            $this->_delegate->actionWasCalled($controller, $action);
        }

        // if for some reason this action has already run, let's not run it again
        // @todo not sure this makes total sense
        if ($can_run && !$controller->hasCompleted($action)) {
            $this->_runAction($controller, $action);
        }

        // process the layout if we can
        // this takes care of handling this view
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

        // turn other exceptions into sonic exceptions
        if (!$e instanceof \Sonic\Exception) {
            $e = new \Sonic\Exception($e->getMessage(), \Sonic\Exception::INTERNAL_SERVER_ERROR, $e);
        }

        // only set the http code if output hasn't started
        if (!headers_sent()) {
            header($e->getHttpCode());
        }

        $json = false;
        $id = null;

        // in turbo mode we have to write the exception markup out to the
        // same div created before the exception was triggered.  this means
        // we have to get the id based on the controller and action that the
        // exception came from
        if ($this->getSetting(self::TURBO) && $this->_layout_processed) {
            $json = true;
            $id = View::generateId($controller, $action);
        }

        $completed = false;

        // controller and action are only null if this is a page not found
        // because we were not able to match any routes.  in all other cases
        // we can get the initial controller and action to determine if it has
        // completed
        if ($controller !== null && $action !== null) {
            $req = $this->getRequest();
            $first_controller = $req->getControllerName();
            $first_action = $req->getAction();
            $completed = $this->getController($first_controller)->hasCompleted($first_action);
        }

        $args = array(
            'exception' => $e,
            'top_level_exception' => !$completed,
            'from_controller' => $controller,
            'from_action' => $action
        );

        return $this->_runController('main', 'error', $args, $json, $id);
    }

    /**
     * determines if we should turn off turbo mode
     *
     * @return bool
     */
    protected function _robotnikWins()
    {
        if ($this->getRequest()->isAjax() || isset($_COOKIE['noturbo']) || isset($_COOKIE['bot'])) {
            return true;
        }

        if (isset($_GET['noturbo'])) {
            setcookie('noturbo', true, time() + 86400);
            return true;
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'Googlebot') !== false) {
            setcookie('bot', true, time() + 86400);
            return true;
        }

        return false;
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

        $delegate = new $delegate;

        if (!$delegate instanceof \Sonic\App\Delegate) {
            throw new \Exception('app delegate of class ' . get_class($delegate) . ' must be instance of \Sonic\App\Delegate');
        }

        $this->_delegate = $delegate;
        $this->_delegate->setApp($this);
        return $this;
    }

    /**
     * loads an extension by name
     *
     * @param string $name
     * @return void
     */
    public function loadExtension($name)
    {
        // if this is already loaded don't do anything
        if ($this->extensionLoaded($name)) {
            return;
        }

        $name = strtolower($name);

        // first grab the extension installation data
        $extensions = $this->getSetting(self::EXTENSION_DATA);
        if (!$extensions) {
            $path = $this->getPath('extensions/installed.json');

            if (file_exists($path)) {
                $extensions = json_decode(file_get_contents($path), true);
                $this->addSetting(self::EXTENSION_DATA, $extensions);
            }
        }

        if (!isset($extensions[$name])) {
            return $this->_handleException(new Exception('trying to load extension "' . $name . '" which is not installed!'));
        }

        // get the data related to this extension
        $data = $extensions[$name];

        // create a delegate object if this extension has one
        $delegate = null;
        if (isset($data['delegate_path']) && isset($data['delegate'])) {
            $this->includeFile('Sonic/Extension/Delegate.php');
            $this->includeFile($this->getPath($data['delegate_path']));
            $delegate = new $data['delegate'];
        }

        if ($delegate) {
            $delegate->extensionStartedLoading();
        }

        $base_path = $this->getPath();

        $core = 'extensions/' . $name . '/Core.php';
        $has_core = isset($data['has_core']) && $data['has_core'];
        $dev = isset($data['dev']) && $data['dev'];

        foreach ($data['files'] as $file) {

            // if the file is not in the extensions or libs directory then skip it
            // we don't want to load controllers/views/etc. here
            $lib_file = strpos($file, 'libs') === 0;
            if (strpos($file, 'extensions') !== 0 && !$lib_file) {
                continue;
            }

            // if this is not a PHP file then skip it
            if (substr($file, -4) != '.php') {
                continue;
            }

            // skip core in dev mode
            if ($dev && $file == $core) {
                continue;
            }

            // if this is a file that is not in libs and not core then skip it
            if (!$dev && !$lib_file && $has_core && $file != $core) {
                continue;
            }

            $this->includeFile($base_path . '/' . $file);

            if ($delegate) {
                $delegate->extensionLoadedFile($file);
            }
        }

        $loaded = $this->getSetting(self::EXTENSIONS_LOADED);
        $loaded[] = $name;
        $this->addSetting(self::EXTENSIONS_LOADED, $loaded);

        if ($delegate) {
            $delegate->extensionFinishedLoading();
        }

        return $this;
    }

    /**
     * determines if an extension is loaded
     *
     * @param string $name
     * @return bool
     */
    public function extensionLoaded($name)
    {
        $loaded = $this->getSetting(self::EXTENSIONS_LOADED);
        return in_array(strtolower($name), $loaded);
    }

    /**
     * gets an extension helper for this extension
     *
     * @param string $name
     * @return \Sonic\Extension\Helper
     */
    public function extension($name)
    {
        $this->includeFile('Sonic/Extension/Helper.php');
        return Extension\Helper::forExtension($name);
    }

    /**
     * pushes over the first domino
     *
     * @param string $mode
     * @param bool $load used for unit tests to prevent fatal errors
     * @return void
     */
    public function start($mode = self::WEB, $load = true)
    {
        if ($this->_delegate) {
            $this->_delegate->appStartedLoading($mode);
        }

        $this->addSetting(self::MODE, $mode);

        // this could use App::includeFile() but it is faster to duplicate
        // that logic here
        if ($load) {
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
        }

        if ($this->getSetting(self::AUTOLOAD)) {
            $this->autoload();
        }

        if ($this->_delegate) {
            $this->_delegate->appFinishedLoading();
        }

        // if we are calling this app from command line then all we want to do
        // is load the core application files
        if ($mode != self::WEB) {
            return;
        }

        if ($this->getSetting(self::TURBO) && $this->_robotnikWins()) {
            $this->addSetting(self::TURBO, false);
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
