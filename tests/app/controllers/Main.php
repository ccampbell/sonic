<?php
namespace Controllers;
use Sonic\Controller;

class Main extends Controller
{
    public function index() {}
    public function benchmarks() {}
    public function quick_start() {}
    public function exception_test()
    {
        throw new \Exception('test exception');
    }
    public function error()
    {
        $this->view->exception = $e =$this->request()->getParam('exception');
        $this->view->show_exception = $this->request()->getParam('top_level_exception');
        $this->view->show_debug = \Sonic\App::getInstance()->isDev();
    }
}
