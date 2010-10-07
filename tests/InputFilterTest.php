<?php
use Sonic\UnitTest\TestCase;
use Sonic\Request;
use Sonic\InputFilter;

class InputFilterTest extends TestCase
{
    private function _filter($name)
    {
        $filter = new InputFilter(new Request());
        return $filter->filter($name);
    }

    public function testConstruct()
    {
        $filter = new InputFilter(new Request());
        $this->isTrue($filter instanceof InputFilter);
    }

    public function testHtml()
    {
        $_GET['message'] = '<p>This is my message</p>';
        $message = $this->_filter('message')->setType('string')->from(Request::GET);

        // tags should be stripped
        $this->isEqual($message, 'This is my message');

        // tags should be allowed
        $message = $this->_filter('message')->setType('string')->allowHtml()->from(Request::GET);
        $this->isEqual($message, $_GET['message']);
    }

    public function testTrim()
    {
        // strings should be trimmed
        $_GET['message'] = ' Woah, cool       ' . "\n\n";
        $message = $this->_filter('message')->setType('string')->from(Request::GET);
        $this->isEqual($message, 'Woah, cool');

        // don't trim
        $message = $this->_filter('message')->setType('string')->noTrim()->from(Request::GET);
        $this->isEqual($message, $_GET['message']);
    }

    public function testDefault()
    {
        // check default value
        $page = $this->_filter('page')->setType('int')->setDefault(1)->from(Request::GET);
        $this->isEqual($page, 1);

        $_GET['page'] = 5;
        $page = $this->_filter('page')->setType('int')->setDefault(1)->from(Request::GET);
        $this->isEqual($page, 5);
    }

    public function testNoDefault()
    {
        // check no arg with no default value
        unset($_POST['brand']);
        $brand = $this->_filter('brand')->setType('string')->from(Request::POST);
        $this->isNull($brand);
    }

    public function testArray()
    {
        // check for value as an array
        $_GET['values'][] = 0;
        $_GET['values'][] = 1;
        $_GET['values'][] = 2;
        $values = $this->_filter('values')->setType('array')->from(Request::GET);
        $this->isArray($values);
        $this->isEqual($values, array(0,1,2));
    }

    public function testInArray()
    {
        // check that a value is in an array
        $_POST['brand'] = 'BMW';
        $brand = $this->_filter('brand')->setType('string')->in(array('BMW', 'Mazda', 'Lexus', 'Mercedes'))->from(Request::POST);
        $this->isEqual($brand, 'BMW');

        // check with value not in array
        $_POST['brand'] = 'Toyota';
        $brand = $this->_filter('brand')->setType('string')->in(array('BMW', 'Mazda', 'Lexus', 'Mercedes'))->from(Request::POST);
        $this->isNull($brand);
    }

    public function testBoolean()
    {
        $cool = $this->_filter('is_cool')->setType('bool')->from(Request::GET);
        $this->isFalse($cool);
        $this->isEqual($cool, null);

        $_GET['is_cool'] = 1;
        $cool = $this->_filter('is_cool')->setType('bool')->from(Request::GET);
        $this->isTrue($cool);
        $this->isEqual($cool, true);
        unset($_GET['is_cool']);

        $cool = $this->_filter('is_cool')->setType('bool')->setDefault(false)->from(Request::GET);
        $this->isFalse($cool);
        $this->isEqual($cool, false);

        $_GET['is_cool'] = 'false';
        $cool = $this->_filter('is_cool')->setType('bool')->from(Request::GET);
        $this->isFalse($cool);
        $this->isEqual($cool, false);
    }

    public function testHex()
    {
        $_GET['md5'] = md5('this is an md5');
        $value = $this->_filter('md5')->setType('hex')->from(Request::GET);
        $this->isEqual($_GET['md5'], $value);

        $_GET['md5'] = 'string';
        $value = $this->_filter('md5')->setType('hex')->from(Request::GET);
        $this->isNull($value);

        // numbers still pass hex
        $_GET['number'] = 1234;
        $value = $this->_filter('number')->setType('hex')->from(Request::GET);
    }

    public function testCustomFunctions()
    {
        $_POST['var'] = 'there are spaces';
        $var = $this->_filter('var')->setType('string')->addFunction(function ($var) {
            return str_replace(' ', '_', $var);
        })->addFunction(function ($var) {
            return substr($var, 0, 9);
        })->from(Request::POST);
        $this->isEqual($var, 'there_are');
    }
}
