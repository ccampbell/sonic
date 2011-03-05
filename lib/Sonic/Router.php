<?php
namespace Sonic;

/**
 * App router - you know, to take the request and map it to a controller and action
 *
 * @category Sonic
 * @package Router
 * @author Craig Campbell
 */
class Router
{
    /**
     * @var string
     */
    protected $_base_uri;

     /**
      * @var string
      */
     protected $_path;

    /**
     * @var string
     */
     protected $_subdomain;

    /**
     * @var array
     */
    protected $_routes;

    /**
     * @var array
     */
    protected $_match;

    /**
     * @var array
     */
    protected $_params = array();

    /**
     * constructor
     *
     * @param Request
     */
    public function __construct($base_uri, $path = null, $subdomain = null)
    {
        $this->_base_uri = $base_uri;
        $this->_path = $path;
        $this->_subdomain = $subdomain;
    }

    /**
     * gets array of routes from the routes.php file
     *
     * @return array
     */
    public function getRoutes()
    {
        if ($this->_routes) {
            return $this->_routes;
        }

        if (!$this->_path) {
            $filename = 'routes.' . (!$this->_subdomain ? 'php' : $this->_subdomain . '.php');
            $this->_path = App::getInstance()->getPath('configs') . '/' . $filename;
        }

        include $this->_path;
        $this->_routes = $routes;

        return $this->_routes;
    }

    /**
     * allows you to set routes to an array
     *
     * @param array $routes
     * @return void
     */
    public function setRoutes(array $routes)
    {
        $this->_routes = $routes;
    }

    /**
     * sets the matching controller / action after they are found
     *
     * @return Router
     */
    protected function _setMatch($match)
    {
        if ($match === null) {
            $this->_match = array(null, null);
            return $this;
        }

        // extra params related to the match
        if (isset($match[2])) {
            $params = $match[2];
            $this->_params = array_merge($this->_params, $params);
        }

        $this->_match = $match;
        return $this;
    }

    /**
     * gets the matching controller / action by processing the routes
     *
     * @return array (looks like: array('controller_name', 'action_name'))
     */
    protected function _getMatch()
    {
        // match is already set, return that
        if ($this->_match !== null) {
            return $this->_match;
        }

        // get the base url
        $base_uri = $this->_base_uri == '/' ? $this->_base_uri : rtrim($this->_base_uri, '/');

        // optimization for the homepage so we don't have to hit the routes
        if ($base_uri === '/' && !$this->_subdomain) {
            $this->_match = array('main', 'index');
            return $this->_match;
        }

        $routes = $this->getRoutes();

        // direct match optimization
        if (isset($routes[$base_uri])) {
            $this->_setMatch($routes[$base_uri]);
            return $this->_match;
        }

        // get all of the keys in the routes file
        $route_keys = array_keys($routes);
        $len = count($route_keys);

        // loop through all of the routes and check for a match
        $match = false;
        for ($i = 0; $i < $len; ++$i) {
            if ($this->_matches($route_keys[$i], $base_uri)) {
                $match = true;

                // stop after the first match!
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

    /**
     * checks if parts of the requested uri match a route uri
     *
     * @param string $route_uri /profile/:user_id
     * @param string $base_uri /profile/25
     * @return bool
     */
    protected function _matches($route_uri, $base_uri)
    {
        $route_bits = explode('/', $route_uri);
        $url_bits = explode('/', $base_uri);

        $route_bit_count = count($route_bits);

        // if the urls don't have the same number of parts then this is not a match
        if ($route_bit_count !== count($url_bits)) {
            return false;
        }

        $params = array();
        for ($i = 1; $i < $route_bit_count; ++$i) {

            // if the first character of this part of the route is a ':' that means this is a parameter
            // let's store it and continue
            $first_char = isset($route_bits[$i][0]) ? $route_bits[$i][0] : null;

            // regular old vars
            if ($first_char == ':' || $first_char == '*') {
                $param = substr($route_bits[$i], 1);
                $params[$param] = $url_bits[$i];
                continue;
            }

            // numeric values
            if ($first_char == '#' && is_numeric($url_bits[$i])) {
                $param = substr($route_bits[$i], 1);
                $params[$param] = $url_bits[$i];
                continue;
            }

            // alpha values
            if ($first_char == '@' && preg_match('/^[a-zA-Z]+$/', $url_bits[$i]) > 0) {
                $param = substr($route_bits[$i], 1);
                $params[$param] = $url_bits[$i];
                continue;
            }

            // if any part of the urls don't match then return false immediately
            if ($route_bits[$i] != $url_bits[$i]) {
                return false;
            }
        }

        $this->_params = $params;
        return true;
    }

    /**
     * gets the name of the controller to run for this request
     *
     * @return string
     */
    public function getController()
    {
        $match = $this->_getMatch();
        return $match[0];
    }

    /**
     * gets the name of the action to run for this request
     *
     * @return string
     */
    public function getAction()
    {
        $match = $this->_getMatch();
        return $match[1];
    }

    /**
     * gets params
     *
     * @return array
     */
    public function getParams()
    {
        return $this->_params;
    }
}
