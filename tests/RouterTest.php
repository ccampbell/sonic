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
        $router = new Router('/');
        $this->isEqual('main', $router->getController());
        $this->isEqual('index', $router->getAction());

        // test homepage on subdomain
        $router = new Router('/', null, 'tests');
        $this->isEqual('tests', $router->getController());
        $this->isEqual('index', $router->getAction());

        // test no route
        $router = new Router('/blah-blah', null, 'tests');
        $this->isEqual(null, $router->getController());
        $this->isEqual(null, $router->getAction());

        // test /random
        $router = new Router('/random', null, 'tests');
        $this->isEqual('tests', $router->getController());
        $this->isEqual('random', $router->getAction());

        $params = $router->getParams();
        $this->isTrue(array_key_exists('ajax', $params));
        $this->isEqual($params['ajax'], true);

        $this->isTrue(array_key_exists('magic', $params));
        $this->isEqual($params['magic'], false);

        // test /random/
        $router = new Router('/random/', null, 'tests');
        $this->isEqual('tests', $router->getController());
        $this->isEqual('random', $router->getAction());

        // test routes with :var
        $router = new Router('/lesson/name-of-lesson', null, 'tests');
        $this->isEqual('lesson', $router->getController());
        $this->isEqual('main', $router->getAction());

        $params = $router->getParams();
        $this->isTrue(array_key_exists('lesson_name', $params));
        $this->isEqual($params['lesson_name'], 'name-of-lesson');

        // test routes with *var
        $router = new Router('/artist/bruce-springsteen', null, 'tests');
        $this->isEqual('artist', $router->getController());
        $this->isEqual('main', $router->getAction());

        $params = $router->getParams();
        $this->isTrue(array_key_exists('name', $params));
        $this->isEqual($params['name'], 'bruce-springsteen');

        // test route with #var
        $router = new Router('/profile/25', null, 'tests');
        $this->isEqual('profile', $router->getController());
        $this->isEqual('user', $router->getAction());

        $params = $router->getParams();
        $this->isTrue(array_key_exists('user_id', $params));
        $this->isEqual($params['user_id'], 25);

        $this->isTrue(array_key_exists('magic', $params));
        $this->isEqual($params['magic'], true);

        // test failed number
        $router = new Router('/profile/whatever', null, 'tests');
        $this->isEqual(null, $router->getController());
        $this->isEqual(null, $router->getAction());

        // test routes with @var
        $router = new Router('/word/awesome', null, 'tests');
        $this->isEqual('word', $router->getController());
        $this->isEqual('main', $router->getAction());

        $params = $router->getParams();
        $this->isTrue(array_key_exists('word', $params));
        $this->isEqual($params['word'], 'awesome');

        // test failed alpha
        $router = new Router('/word/word123', null, 'tests');
        $this->isEqual(null, $router->getController());
        $this->isEqual(null, $router->getAction());

        // test multiple params in same route
        $router = new Router('/word/awesome/translate/japanese', null, 'tests');
        $this->isEqual('word', $router->getController());
        $this->isEqual('translate', $router->getAction());

        $params = $router->getParams();
        $this->isTrue(array_key_exists('word', $params));
        $this->isEqual($params['word'], 'awesome');

        $this->isTrue(array_key_exists('language', $params));
        $this->isEqual($params['language'], 'japanese');
    }
}
