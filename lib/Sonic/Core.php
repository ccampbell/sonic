<?php
/**
 * combined core files to speed up your application (with comments stripped)
 *
 * includes App.php, Request.php, Router.php, Controller.php, View.php, Layout.php, Exception.php
 *
 * @author Craig Campbell
 *
 * last commit: 795f581366469e6c37c539134c8c288920776e30
 * generated: 2010-08-14 11:29:21 EST
 */
namespace Sonic;

class App
{
    const WEB='www';
    const COMMAND_LINE='cli';
    protected static $_instance;
    protected $_request;
    protected $_paths=array();
    protected $_controllers=array();
    protected $_layout_processed=false;
    protected $_base_path;
    protected $_environment;
    protected $_settings=array('mode'=> self::WEB,
                               'autoload'=> false,
                               'config_file'=> 'php',
                               'devs'=> array('dev','development'));
    private function __construct() {}
    public static function getInstance()
    {
        if (self::$_instance===null) {
            self::$_instance=new App();
        }
        return self::$_instance;
    }
    public function autoloader($class_name)
    {
        include str_replace('\\',DIRECTORY_SEPARATOR,$class_name).'.php';
    }
    public function autoload()
    {
        spl_autoload_register(array($this,'autoloader'));
    }
    public function enableAutoload()
    {
        $this->addSetting('autoload',true);
    }
    public function addSetting($key,$value)
    {
        $this->_settings[$key]=$value;
    }
    public function getSetting($name)
    {
        if (!isset($this->_settings[$name])) {
            return null;
        }
        return $this->_settings[$name];
    }
    public static function getConfig($path=null)
    {
        $environment=self::getInstance()->getEnvironment();
        $cache_key=__METHOD__.'_'.$path.'_'.$environment;
        if ($config=Cache\Instance::get($cache_key)) {
            return $config;
        }
        if ($path===null) {
            $type=self::getInstance()->getSetting('config_file');
            $path=self::getInstance()->getPath('configs').DIRECTORY_SEPARATOR.'app.'.$type;
        }
        if (!self::isDev()&&($config=apc_fetch($cache_key))) {
            Cache\Instance::set($cache_key,$config);
            return $config;
        }
        $config=new Config($path,$environment,$type);
        Cache\Instance::set($cache_key,$config);
        apc_store($cache_key,$config,Util::toSeconds('24 hours'));
        return $config;
    }
    public static function getMemcache($pool='default')
    {
        return Cache\Factory::getMemcache($pool);
    }
    public static function getMemcached($pool='default')
    {
        return Cache\Factory::getMemcached($pool);
    }
    public static function isDev()
    {
        $app=self::getInstance();
        return in_array($app->getEnvironment(),$app->getSetting('devs'));
    }
    public function getEnvironment()
    {
        if ($this->_environment!==null) {
            return $this->_environment;
        }
        if ($environment=getenv('ENVIRONMENT')) {
            $this->_environment=$environment;
            return $environment;
        }
        throw new Exception('ENVIRONMENT variable is not set! check your apache config');
    }
    public function getAllActions()
    {
        $actions=array();
        foreach ($this->_controllers as $controller) {
            foreach ($controller->getActionsCompleted() as $action) {
                $actions[]=$controller->name().'::'.$action;
            }
        }
        return $actions;
    }
    public function getRequest()
    {
        if ($this->_request===null) {
            $this->_request=new Request();
        }
        return $this->_request;
    }
    public function getBasePath()
    {
        if ($this->_base_path!==null) {
            return $this->_base_path;
        }
        switch ($this->getSetting('mode')) {
            case self::COMMAND_LINE:
                $this->_base_path=str_replace('/lib','',get_include_path());
                break;
            default:
                $document_root=$this->getRequest()->getServer('DOCUMENT_ROOT');
                $this->_base_path=str_replace('/public_html','',$document_root);
        }
        return $this->_base_path;
    }
    public function getPath($dir=null)
    {
        $cache_key=__METHOD__.'_'.$dir;
        if (isset($this->_paths[$cache_key])) {
            return $this->_paths[$cache_key];
        }
        $base_path=$this->getBasePath();
        if ($dir!==null) {
            $base_path .='/'.$dir;
        }
        $this->_paths[$cache_key]=$base_path;
        return $this->_paths[$cache_key];
    }
    public function getController($name)
    {
        if (isset($this->_controllers[$name])) {
            return $this->_controllers[$name];
        }
        include $this->getPath('controllers').'/'.$name.'.php';
        $class_name='\Controllers\\'.$name;
        $this->_controllers[$name]=new $class_name();
        $this->_controllers[$name]->name($name);
        $this->_controllers[$name]->request($this->getRequest());
        return $this->_controllers[$name];
    }
    protected function _runController($controller_name,$action,$args=array())
    {
        $this->getRequest()->addParams($args);
        $controller=$this->getController($controller_name);
        $controller->setView($action);
        $view=$controller->getView();
        $view->addVars($args);
        $run_action=false;
        if (!$controller->hasCompleted('init')) {
            $run_action=true;
            $controller->actionComplete('init');
            $controller->init();
        }
        if ($run_action||!$controller->hasCompleted($action)) {
            $controller->$action();
            $controller->actionComplete($action);
        }
        if (!$this->_layout_processed&&$controller->hasLayout()&&count($this->_controllers)===1) {
            $this->_layout_processed=true;
            $layout=$controller->getLayout();
            $layout->topView($view);
            return $layout->output();
        }
        $view->output();
    }
    public function runController($controller_name,$action,$args=array())
    {
        try {
            $this->_runController($controller_name,$action,$args);
        } catch (\Exception $e) {
            $this->_handleException($e,$controller_name,$action);
        }
    }
    protected function _handleException(\Exception $e,$controller=null,$action=null)
    {
        $this->_runController('main','error',array('exception'=> $e,'from_controller'=> $controller,'from_action'=> $action));
    }
    public function start($mode=self::WEB)
    {
        $this->addSetting('mode',$mode);
        if ($this->getSetting('autoload')) {
            $this->autoload();
        }
        if ($mode!=self::WEB) {
            return;
        }
        try {
            $controller=$this->getRequest()->getControllerName();
            $action=$this->getRequest()->getAction();
        } catch (\Exception $e) {
            return $this->_handleException($e);
        }
        $this->runController($controller,$action);
    }
}
use \Sonic\Exception;
class Request
{
    protected $_caches=array();
    protected $_params=array();
    protected $_router;
    protected $_controller;
    protected $_controller_name;
    protected $_action;
    public function getBaseUri()
    {
        if (isset($this->_caches['base_uri'])) {
            return $this->_caches['base_uri'];
        }
        if (($uri=$this->getServer('REDIRECT_URL'))!==null) {
            $this->_caches['base_uri']=$uri=='/' ? $uri : rtrim($uri,'/');
            return $this->_caches['base_uri'];
        }
        $bits=explode('?',$this->getServer('REQUEST_URI'));
        $this->_caches['base_uri']=$bits[0]=='/' ? $bits[0] : rtrim($bits[0],'/');
        return $this->_caches['base_uri'];
    }
    public function getServer($name)
    {
        if (!isset($_SERVER[$name])) {
            return null;
        }
        return $_SERVER[$name];
    }
    public function getRouter()
    {
        if ($this->_router===null) {
            $this->_router=new Router($this);
        }
        return $this->_router;
    }
    public function getControllerName()
    {
        if ($this->_controller_name!==null) {
            return $this->_controller_name;
        }
        $this->_controller_name=$this->getRouter()->getController();
        if (!$this->_controller_name) {
            throw new Exception('page not found at '.$this->getBaseUri(),EXCEPTION::NOT_FOUND);
        }
        return $this->_controller_name;
    }
    public function getAction()
    {
        if ($this->_action!==null) {
            return $this->_action;
        }
        $this->_action=$this->getRouter()->getAction();
        if (!$this->_action) {
            throw new Exception('page not found at '.$this->getBaseUri(),EXCEPTION::NOT_FOUND);
        }
        return $this->_action;
    }
    public function addParams(array $params) {
        foreach ($params as $key=> $value) {
            $this->addParam($key,$value);
        }
    }
    public function addParam($key,$value)
    {
        $this->_params[$key]=$value;
        return $this;
    }
    public function getParam($name)
    {
        if (!isset($this->_params[$name])) {
            return null;
        }
        return $this->_params[$name];
    }
}

class Router
{
    protected $_routes;
    protected $_request;
    protected $_match;
    public function __construct(Request $request)
    {
        $this->_request=$request;
    }
    public function getRoutes()
    {
        if ($this->_routes===null) {
            $path=App::getInstance()->getPath('configs').'/routes.php';
            include $path;
            $this->_routes=$routes;
        }
        return $this->_routes;
    }
    protected function _setMatch($match)
    {
        if ($match===null) {
            $this->_match=array(null,null);
            return $this;
        }
        $this->_match=$match;
        return $this;
    }
    protected function _getMatch()
    {
        if ($this->_match!==null) {
            return $this->_match;
        }
        $base_uri=$this->_request->getBaseUri();
        if ($base_uri==='/') {
            $this->_match=array('main','index');
            return $this->_match;
        }
        $routes=$this->getRoutes();
        if (isset($routes[$base_uri])) {
            $this->_setMatch($routes[$base_uri]);
            return $this->_match;
        }
        $route_keys=array_keys($routes);
        $len=count($route_keys);
        $base_bits=explode('/',$base_uri);
        $match=false;
        for ($i=0; $i < $len; ++$i) {
            if ($this->_matches(explode('/',$route_keys[$i]),$base_bits)) {
                $match=true;
                break;
            }
        }
        if ($match) {
            $this->_setMatch($routes[$route_keys[$i]]);
            return $this->_match;
        }
        $this->_setMatch(null);
        return $this->_match;
    }
    protected function _matches($route_bits,$url_bits)
    {
        $route_bit_count=count($route_bits);
        if ($route_bit_count!==count($url_bits)) {
            return false;
        }
        $match=true;
        $params=array();
        for ($i=1; $i < $route_bit_count; ++$i) {
            if ($route_bits[$i][0]===':') {
                $param=substr($route_bits[$i],1);
                $params[$param]=$url_bits[$i];
                continue;
            }
            if ($route_bits[$i]!=$url_bits[$i]) {
                return false;
            }
        }
        $this->_request->addParams($params);
        return true;
    }
    public function getController()
    {
        $match=$this->_getMatch();
        return $match[0];
    }
    public function getAction()
    {
        $match=$this->_getMatch();
        return $match[1];
    }
}

class Controller
{
    protected $_name;
    protected $_view_name;
    protected $_view;
    protected $_layout;
    protected $_layout_name=Layout::MAIN;
    protected $_request;
    protected $_actions_completed=array();
    public function __get($var)
    {
        if ($var==='view') {
            return $this->getView();
        }
        if ($var==='layout') {
            return $this->getLayout();
        }
        throw new Exception('only views and layouts are magic');
    }
    final public function name($name=null)
    {
        if ($name!==null) {
            $this->_name=$name;
        }
        return $this->_name;
    }
    final public function setView($name)
    {
        if ($this->_view_name!==$name) {
            $this->_view_name=$name;
            $this->_view===null ?: $this->getView()->path($this->getViewPath());
        }
        $this->_layout_name=Layout::MAIN;
        return $this;
    }
    public function request(Request $request=null)
    {
        if ($request!==null) {
            $this->_request=$request;
        }
        return $this->_request;
    }
    public function init() {}
    public function actionComplete($action)
    {
        $this->_actions_completed[$action]=true;
        return $this;
    }
    public function getActionsCompleted()
    {
        return array_keys($this->_actions_completed);
    }
    public function hasCompleted($action)
    {
        return isset($this->_actions_completed[$action]);
    }
    public function disableLayout()
    {
        $this->_layout_name=null;
        return $this;
    }
    public function disableView()
    {
        $this->getView()->disable();
    }
    public function hasLayout()
    {
        return $this->_layout_name!==null;
    }
    public function getLayout()
    {
        if ($this->_layout!==null) {
            return $this->_layout;
        }
        $layout_dir=App::getInstance()->getPath('views/layouts');
        $layout=new Layout($layout_dir.'/'.$this->_layout_name.'.phtml');
        $this->_layout=$layout;
        return $this->_layout;
    }
    final public function getViewPath()
    {
        return App::getInstance()->getPath('views').'/'.$this->_name.'/'.$this->_view_name.'.phtml';
    }
    public function getView()
    {
        if ($this->_view!==null) {
            return $this->_view;
        }
        $this->_view=new View($this->getViewPath());
        $this->_view->setActiveController($this->_name);
        return $this->_view;
    }
}

class View
{
    protected $_active_controller;
    protected $_path;
    protected $_title;
    protected $_html;
    protected $_disabled=false;
    public function __construct($path)
    {
        $this->path($path);
    }
    public function __get($var)
    {
        if (!isset($this->$var)) {
            return null;
        }
        return $this->$var;
    }
    public function path($path=null)
    {
        if ($path!==null) {
            $this->_path=$path;
        }
        return $this->_path;
    }
    public function title($title=null)
    {
        if ($title!==null) {
            $this->_title=$title;
        }
        return $this->_title;
    }
    public function addVars(array $args)
    {
        foreach ($args as $key=> $value) {
            $this->$key=$value;
        }
    }
    public function setActiveController($name)
    {
        $this->_active_controller=$name;
    }
    public function disable()
    {
        $this->_disabled=true;
    }
    public function render($controller,$action=null,$args=array())
    {
        if ($action===null||is_array($action)) {
            $args=(array) $action;
            $action=$controller;
            $controller=$this->_active_controller;
        }
        App::getInstance()->runController($controller,$action,$args);
    }
    public function buffer()
    {
        if ($this->_disabled) {
            return;
        }
        ob_start();
        $this->output();
        $this->_html=ob_get_contents();
        ob_end_clean();
    }
    public function getHtml()
    {
        return $this->_html;
    }
    public function output()
    {
        if ($this->_disabled) {
            return;
        }
        if ($this->getHtml()!==null) {
            echo $this->getHtml();
            return;
        }
        include $this->_path;
    }
}

class Layout extends View
{
    const MAIN='main';
    protected $_top_view;
    public function output()
    {
        if ($this->topView()!==null) {
            $this->topView()->buffer();
        }
        parent::output();
    }
    public function topView(View $view=null)
    {
        if ($this->_top_view===null&&$view!==null) {
            $this->_top_view=$view;
        }
        return $this->_top_view;
    }
}

class Exception extends \Exception
{
    const INTERNAL_SERVER_ERROR=0;
    const NOT_FOUND=1;
    const FORBIDDEN=2;
    const UNAUTHORIZED=3;
    public function getDisplayMessage()
    {
        switch ($this->code) {
            case self::NOT_FOUND:
                return 'The page you were looking for could not be found.';
                break;
            case self::FORBIDDEN:
                return 'You do not have permission to view this page.';
                break;
            case self::UNAUTHORIZED:
                return 'This page requires login.';
                break;
            default:
                return 'Some kind of error occured.';
                break;
        }
    }
    public function getHttpCode()
    {
        switch ($this->code) {
            case self::NOT_FOUND:
                return 'HTTP/1.1 404 Not Found';
                break;
            case self::FORBIDDEN:
                return 'HTTP/1.1 403 Forbidden';
                break;
            case self::UNAUTHORIZED:
                return 'HTTP/1.1 401 Unauthorized';
                break;
            default:
                return 'HTTP/1.1 500 Internal Server Error';
                break;
        }
    }
}
