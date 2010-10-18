<?php
use Sonic\UnitTest\TestCase;
use Sonic\Layout, Sonic\App;

class LayoutTest extends TestCase
{
    public function testGetTitle()
    {
        $app = App::getInstance();
        $layout = new Layout($app->getPath('views') . '/layouts/main.phtml');
        $title = $layout->getTitle('this is my title');
        $this->isEqual($title, 'this is my title');

        $layout->setTitlePattern('Unit Tests : ${title}');
        $title = $layout->getTitle('Layout');
        $this->isEqual($title, 'Unit Tests : Layout');
    }

    public function testNoTurboUrl()
    {
        $_SERVER['REQUEST_URI'] = '/test-url-one';
        $layout = new Layout('');
        $url = $layout->noTurboUrl();
        $this->isEqual($url, '/test-url-one?noturbo=1');

        $_SERVER['REQUEST_URI'] = '/another/test/?a=1&b=0';
        $url = $layout->noTurboUrl();
        $this->isEqual($url, '/another/test/?a=1&b=0&noturbo=1');
    }

    public function testTurbo()
    {
        // start output buffering so there is something to clean
        ob_start();

        // this isn't going to do anything, but should execute it for code coverage
        $layout = new Layout('');
        $turbo = $layout->turbo();
    }
}
