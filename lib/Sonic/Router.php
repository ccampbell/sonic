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
     * @var array
     */
    protected $_routes;

    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var array
     */
    protected $_match;

    /**
     * @var string
     */
     protected $_subdomain;

    /**
     * constructor
     *
     * @param Request
     */
    public function __construct(Request $request, $subdomain = null)
    {
        $this->_request = $request;
        $this->_subdomain = $subdomain;
    }

    /**
     * gets array of routes from the routes.php file
     *
     * @return array
     */
    public function getRoutes()
    {
        if ($this->_routes === null) {
            $filename = 'routes.' . (!$this->_subdomain ? 'php' : $this->_subdomain . '.php');
            $path = App::getInstance()->getPath('configs') . '/' . $filename;
            include $path;
            $this->_routes = $routes;
        }

        return $this->_routes;
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
        $base_uri = $this->_request->getBaseUri();

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

        // explode the base uri for comparisons
        $base_bits = explode('/', $base_uri);

        // loop through all of the routes and check for a match
        $match = false;
        for ($i = 0; $i < $len; ++$i) {
            if ($this->_matches(explode('/', $route_keys[$i]), $base_bits)) {
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
     * @param array $route_bits (something like array('', 'profile', ':user_id'))
     * @param array $url_bits (something like array('', 'profile', '25'))
     * @return bool
     */
    protected function _matches($route_bits, $url_bits)
    {
        $route_bit_count = count($route_bits);

        // if the urls don't have the same number of parts then this is not a match
        if ($route_bit_count !== count($url_bits)) {
            return false;
        }

        $match = true;

        $params = array();
        for ($i = 1; $i < $route_bit_count; ++$i) {

            // if the first character of this part of the route is a ':' that means this is a parameter
            // let's store it and continue
            if ($route_bits[$i][0] === ':') {
                $param = substr($route_bits[$i], 1);
                $params[$param] = $url_bits[$i];
                continue;
            }

            // if any part of the urls don't match then return false immediately
            if ($route_bits[$i] != $url_bits[$i]) {
                return false;
            }
        }

        $this->_request->addParams($params);
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
}
