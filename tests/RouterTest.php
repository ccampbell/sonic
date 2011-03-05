<?php
use Sonic\UnitTest\TestCase;
use Sonic\Router, Sonic\App;

class RouterTest extends TestCase
{
    public function testConstruct()
    {
        $app = App::getInstance();
        $app->setBasePath($app->getPath('tests') . '/app');
        $request = $app->getInstance()->getRequest();

        $router = new Router($request);
        $this->isTrue($router instanceof Router);
    }

    public function testGetRoutes()
    {
        $app = App::getInstance();
        $request = $app->getRequest();

        $router = new Router($request);
        $routes = $router->getRoutes();

        $this->isArray($routes);
    }

    public function testGetRoutesForSubdomain()
    {
        $app = App::getInstance();
        $request = $app->getRequest();

        $router = new Router($request, null, 'tests');
        $routes = $router->getRoutes();

        $this->isArray($routes);
    }

    public function testGetRoutesFromPath()
    {
        $app = App::getInstance();
        $request = $app->getRequest();

        $router = new Router($request, $path);
        $routes = $router->getRoutes();

        $this->isArray($routes);
    }

    public function testGetRoutesFromArray()
    {
        $app = App::getInstance();
        $request = $app->getRequest();
        $router = new Router($request);

        $routes = array(
            '/' => array('main', 'index'),
            '/new-route' => array('main', 'new')
        );

        $router->setRoutes($routes);
        $router_routes = $router->getRoutes();

        $this->isEqual($routes, $router_routes);
    }

    public function testRoutes()
    {
        $app = App::getInstance();
        $request = $app->getRequest();

        // test homepage
        $request->reset();
        $_SERVER['REQUEST_URI'] = '/';
        $router = new Router($request);
        $this->isEqual('main', $router->getController());
        $this->isEqual('index', $router->getAction());

        // test homepage on subdomain
        $request->reset();
        $_SERVER['REQUEST_URI'] = '/';
        $router = new Router($request, null, 'tests');
        $this->isEqual('tests', $router->getController());
        $this->isEqual('index', $router->getAction());

        // test no route
        $request->reset();
        $_SERVER['REQUEST_URI'] = '/blah-blah';
        $router = new Router($request, null, 'tests');
        $this->isEqual(null, $router->getController());
        $this->isEqual(null, $router->getAction());

        // test /random
        $request->reset();
        $_SERVER['REQUEST_URI'] = '/random';
        $router = new Router($request, null, 'tests');
        $this->isEqual('tests', $router->getController());
        $this->isEqual('random', $router->getAction());

        $params = $request->getParams();
        $this->isTrue(array_key_exists('ajax', $params));
        $this->isEqual($params['ajax'], true);

        $this->isTrue(array_key_exists('magic', $params));
        $this->isEqual($params['magic'], false);

        // test /random/
        $request->reset();
        $_SERVER['REQUEST_URI'] = '/random/';
        $router = new Router($request, null, 'tests');
        $this->isEqual('tests', $router->getController());
        $this->isEqual('random', $router->getAction());

        // test route with var
        $request->reset();
        $_SERVER['REQUEST_URI'] = '/profile/25';
        $router = new Router($request, null, 'tests');
        $this->isEqual('profile', $router->getController());
        $this->isEqual('user', $router->getAction());

        $params = $request->getParams();
        $this->isTrue(array_key_exists('user_id', $params));
        $this->isEqual($params['user_id'], 25);

        $this->isTrue(array_key_exists('magic', $params));
        $this->isEqual($params['magic'], true);
    }
}
