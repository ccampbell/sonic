<?php
namespace Sonic;

/**
 * Request object - you know, for handling $_GET, $_POST, and other params
 *
 * @package Sonic
 * @subpackage Request
 * @author Craig Campbell
 */
class Request
{
    /**
     * @var array
     */
    protected $_caches = array();

    /**
     * @var array
     */
    protected $_params = array();

    /**
     * @var Router
     */
    protected $_router;

    /**
     * @var Controller
     */
    protected $_controller;

    /**
     * @var string
     */
    protected $_controller_name;

    /**
     * @var string
     */
    protected $_action;

    /**
     * gets the base uri for the request
     * for example /profile?id=25 would return "/profile"
     *
     * @return string
     */
    public function getBaseUri()
    {
        if (isset($this->_caches['base_uri'])) {
            return $this->_caches['base_uri'];
        }

        // if redirect url is present use that to avoid extra processing
        if (($uri = $this->getServer('REDIRECT_URL')) !== null) {
            $this->_caches['base_uri'] = $uri == '/' ? $uri : rtrim($uri, '/');
            return $this->_caches['base_uri'];
        }

        $bits = explode('?', $this->getServer('REQUEST_URI'));
        $this->_caches['base_uri'] = $bits[0] == '/' ? $bits[0] : rtrim($bits[0], '/');

        return $this->_caches['base_uri'];
    }

    /**
     * gets a server param
     *
     * @param string $name
     * @return mixed
     */
    public function getServer($name)
    {
        if (!isset($_SERVER[$name])) {
            return null;
        }

        return $_SERVER[$name];
    }

    /**
     * gets the router object
     *
     * @return Router
     */
    public function getRouter()
    {
        if ($this->_router === null) {
            $this->_router = new Router($this);
        }

        return $this->_router;
    }

    /**
     * gets the controller name from the router after the routes have been processed
     *
     * @return string
     */
    public function getControllerName()
    {
        if ($this->_controller_name === null) {
            $this->_controller_name = $this->getRouter()->getController() ?: 'main';
        }

        return $this->_controller_name;
    }

    /**
     * gets the action name from the Router after the routes have been processed
     *
     * @return string
     */
    public function getAction()
    {
        if ($this->_action === null) {
            $this->_action = $this->getRouter()->getAction() ?: 'error';
        }

        return $this->_action;
    }

    /**
     * adds request params
     *
     * @param array
     * @return void
     */
    public function addParams(array $params) {
        foreach ($params as $key => $value) {
            $this->addParam($key, $value);
        }
    }

    /**
     * adds a single request param
     *
     * @param string $key
     * @param mixed $value
     * @return Request
     */
    public function addParam($key, $value)
    {
        $this->_params[$key] = $value;
        return $this;
    }

    /**
     * gets a param from the request
     *
     * @param string $name parameter name
     * @return mixed
     */
    public function getParam($name)
    {
        if (!isset($this->_params[$name])) {
            return null;
        }
        return $this->_params[$name];
    }
}
