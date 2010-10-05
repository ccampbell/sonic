<?php
use Sonic\App;
use Sonic\UnitTest\TestCase;
use Sonic\Layout, Sonic\View, Sonic\Request;

class ControllerTest extends TestCase
{
    public function test__get()
    {
        $controller = new Controllers\Main();
        $layout = $controller->layout;
        $this->isTrue($layout instanceof Layout);

        $view = $controller->view;
        $this->isTrue($view instanceof View);

        $this->isException('Sonic\Exception');
        $controller->other_var;
    }

    public function testName()
    {
        $controller = new Controllers\Main();
        $controller->name('Main');
        $name = $controller->name();
        $this->isEqual($name, 'Main');
    }

    public function testSetView()
    {
        $controller = new Controllers\Main();
        $controller->setView('test');
    }

    public function testRequest()
    {
        $controller = new Controllers\Main();
        $request = new Request();
        $controller->request($request);
        $controller_request = $controller->request();
        $this->isExact($request, $controller_request);
    }

    public function testCompletedActions()
    {
        $controller = new Controllers\Main();
        $completed = $controller->getActionsCompleted();
        $this->isArray($completed);
        $this->isEqual(count($completed), 0);

        $controller->actionComplete('index');
        $completed = $controller->getActionsCompleted();
        $this->isArray($completed);
        $this->isEqual(count($completed), 1);
        $this->isEqual($completed[0], 'index');

        $result = $controller->hasCompleted('error');
        $this->isFalse($result);

        $result = $controller->hasCompleted('index');
        $this->isTrue($result);
    }

    public function testLayout()
    {
        $controller = new Controllers\Main();
        $has_layout = $controller->hasLayout();
        $this->isTrue($has_layout);
        $layout = $controller->getLayout();
        $this->isTrue($layout instanceof Layout);

        $controller->disableLayout();
        $has_layout = $controller->hasLayout();
        $this->isFalse($has_layout);

        $this->isEqual($controller->layout, $controller->getLayout());
    }

    public function testDisableView()
    {
        $controller = new Controllers\Main();
        $controller->disableView();
    }

    public function testSetLayout()
    {
        $controller = new Controllers\Main();
        $controller->setLayout('test');
        $layout = $controller->getLayout();
        $this->isTrue($layout instanceof Layout);
    }

    public function testGetViewPath()
    {
        $controller = new Controllers\Main();
        $controller->name('Main');
        $this->isFalse(file_exists($controller->getViewPath()));

        $controller->setView('test');
        $path = $controller->getViewPath();
        $this->isTrue(file_exists($path));

        $app = App::getInstance();
        $view_path = $app->getPath('views');
        $this->isEqual($view_path . '/Main/test.phtml', $path);
    }

    public function testGetView()
    {
        $controller = new Controllers\Main();
        $this->isEqual($controller->getView(), $controller->view);
        $this->isTrue($controller->getView() instanceof View);
    }

    public function test__toString()
    {
        $controller = new Controllers\Main();
        $name = (string) $controller;
        $this->isEqual('Controllers\Main', $name);
    }

    public function testFilter()
    {
        $controller = new Controllers\Main();
        $controller->request(new Request);
        $filter = $controller->filter('variable');
        $this->isTrue($filter instanceof Sonic\InputFilter);

        $second_filter = $controller->filter('other_variable');
        $this->isExact($filter, $second_filter);
    }
}
