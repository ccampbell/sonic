<?php
use Sonic\App;
use Sonic\UnitTest\TestCase;

class AppTest extends TestCase
{
    public function testGetInstance()
    {
        $app = App::getInstance();

        if (!extension_loaded('apc')) {
            $app->addSetting(App::DISABLE_APC, true);
        }

        $this->isTrue($app instanceof App);
    }

    public function testIncludeFile()
    {
        $app = App::getInstance();
        $success = $app->includeFile('Sonic/Database.php');
        $this->isTrue($success);

        // make sure it was already included
        $success = $app->includeFile('Sonic/Database.php');
        $this->isFalse($success);
    }

    public function testSettings()
    {
        $app = App::getInstance();
        $app->addSetting('unit_test', true);
        $unit_test = $app->getSetting('unit_test');
        $this->isTrue($unit_test);

        $random_setting = $app->getSetting('random_setting');
        $this->isNull($random_setting);

        // test that we are in CLI
        $mode = $app->getSetting(App::MODE);
        $this->isEqual($mode, App::COMMAND_LINE);
    }

    public function testIsDev()
    {
        $app = App::getInstance();
        $this->isTrue($app->isDev());

        $current_devs = $app->getSetting(App::DEVS);

        $app->addSetting(App::DEVS, array());
        $this->isFalse($app->isDev());

        $app->addSetting(App::DEVS, $current_devs);
    }

    public function testGetEnvironment()
    {
        $app = App::getInstance();
        $env = $app->getEnvironment();
        $this->isNotNull($env);

        putenv('ENVIRONMENT');
        $app->setEnvironment(null);

        $this->isException('Sonic\Exception');
        $app->getEnvironment();
    }

    public function testGetRequest()
    {
        $app = App::getInstance();
        $request = $app->getRequest();
        $this->isTrue($request instanceof Sonic\Request);
    }

    public function testGetBasePath()
    {
        $app = App::getInstance();
        $path = $app->getBasePath();
        $this->isNotNull($path);

        $base_path = str_replace('/tests/AppTest.php', '', realpath(__FILE__));
        $this->isEqual($path, $base_path);

        $base_path_again = $app->getBasePath();
        $this->isEqual($base_path_again, $base_path);
    }

    public function testGetPath()
    {
        $app = App::getInstance();
        $path = $app->getPath();
        $this->isEqual($path, $app->getBasePath());

        $lib_path = $app->getPath('lib');
        $this->isEqual($app->getBasePath() . '/lib', $lib_path);

        // test it is the same when pulled from cache
        $this->isEqual($app->getPath('lib'), $lib_path);
    }

    public function testGetController()
    {
        $app = App::getInstance();
        $app->setBasePath($app->getPath('tests') . '/app');
        $controller = $app->getController('main');
        $this->isTrue($controller instanceof Sonic\Controller);

        // when pulled from cache make sure it is the same
        $this->isExact($controller, $app->getController('main'));
    }

    public function testGetConfig()
    {
        $app = App::getInstance();
        $app->setEnvironment('dev');

        $config = $app->getConfig();
        $this->isTrue($config instanceof Sonic\Config);
        $this->isEqual($app->getConfig(), $config);

        // for production see if we pull from apc
        $app->setEnvironment('production');
        $config = $app->getConfig();
        $this->isTrue($config instanceof Sonic\Config);

        $app->setEnvironment('dev');
    }

    public function testSetDelegate()
    {
        $app = App::getInstance();
        $app->setDelegate('Tests\app\lib\Delegate');

        // bad delegate file
        $this->isException('Exception');
        $app->setDelegate('Tests\app\lib\DelegateBad');
    }

    public function testSetPath()
    {
        $app = App::getInstance();
        $controllers_path = $app->getPath('controllers');
        $app->setPath('controllers', 'test');
        $new_controller_path = $app->getPath('controllers');
        $this->isEqual($new_controller_path, 'test');

        $app->setPath('controllers', $controllers);
    }

    public function testStart()
    {
        $app = App::getInstance();
        $app->outputStarted(true);

        // requesting home page
        ob_start();
        $_SERVER['REQUEST_URI'] = '/';
        $app->start(App::WEB, false);

        // request a page that doesn't exist
        $_SERVER['REQUEST_URI'] = '/doesnotexist';
        $app->getRequest()->reset();
        $app->start(App::WEB, false);

        // test an exception thrown in a controller
        $_SERVER['REQUEST_URI'] = '/exception-test';
        $app->getRequest()->reset();
        $app->start(App::WEB, false);

        // test turbo mode
        $app->getRequest()->reset();
        $app->addSetting(App::TURBO, true);
        $app->start(App::WEB, false);
        $app->processViewQueue();

        // make sure turbo is turned off for ajax requests
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $app->getRequest()->reset();
        $app->addSetting(App::TURBO, true);
        $app->start(App::WEB, false);
        $this->isFalse($app->getSetting(App::TURBO));
        $app->processViewQueue();

        // exception in turbo mode
        $_SERVER['REQUEST_URI'] = '/blah';
        $app->getRequest()->reset();
        $app->addSetting(App::TURBO, true);
        $app->start(App::WEB, false);
        $app->processViewQueue();
        ob_end_clean();
    }

    public function testProcessViewQueue()
    {
        $app = App::getInstance();
        $app->queueView('main', 'index');

        ob_start();
        $app->getRequest()->reset();
        $app->addSetting(App::TURBO, true);
        $app->processViewQueue();

        $app->addSetting(App::TURBO, false);
        $app->processViewQueue();
        ob_end_clean();
    }
}
