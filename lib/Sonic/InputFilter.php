<?php
namespace Sonic;
use Sonic\Request;

/**
 * InputFilter
 *
 * @category Sonic
 * @package InputFilter
 * @author Craig Campbell
 */
class InputFilter
{
    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var string
     */
    protected $_name;

    /**
     * @var string
     */
    protected $_type = 'string';

    /**
     * @var mixed
     */
    protected $_default;

    /**
     * @var array
     */
    protected $_options;

    /**
     * @var array
     */
    protected $_functions = array();

    /**
     * @var bool
     */
    protected $_trim = true;

    /**
     * @var bool
     */
    protected $_strip_html = true;

    /**
     * constructor
     *
     * @param Request $request
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->_request = $request;
    }

    /**
     * resets filter so we can use the same filter for many vars
     *
     * @return InputFilter
     */
    protected function _reset()
    {
        $this->_name = null;
        $this->_type = null;
        $this->_default = null;
        $this->_options = null;
        $this->_functions = array();
        return $this;
    }

    /**
     * sets up the filter for the next param
     *
     * @param string $name
     * @return InputFilter
     */
    public function filter($name)
    {
        $this->_reset();
        $this->_name = $name;
        return $this;
    }

    /**
     * turns off trim
     *
     * @return InputFilter
     */
    public function noTrim()
    {
        $this->_trim = false;
        return $this;
    }

    /**
     * allows html
     *
     * @return InputFilter
     */
    public function allowHtml()
    {
        $this->_strip_html = false;
        return $this;
    }

    /**
     * sets type for this variable
     *
     * @param string $type
     * @return InputFilter
     */
    public function setType($type)
    {
        $this->_type = $type;
        return $this;
    }

    /**
     * sets default value for this filter
     *
     * @param mixed $default
     * @return InputFilter
     */
    public function setDefault($default)
    {
        $this->_default = $default;
        return $this;
    }

    /**
     * limits this input to be in the array specified
     *
     * @param array $options
     * @return InputFilter
     */
    public function in(array $options)
    {
        $this->_options = $options;
        return $this;
    }

    /**
     * adds a function to the input filter
     *
     * @param $function string or closure
     * @return InputFilter
     */
    public function addFunction($function)
    {
        $this->_functions[] = $function;
        return $this;
    }

    /**
     * gets the value after filtering
     *
     * @param string $type
     * @return mixed
     */
    public function from($type = Request::GET)
    {
        $arg = $this->_request->getParam($this->_name, $type);
        return $this->_applyFilters($arg);
    }

    /**
     * does the actual filtering of the argument
     *
     * @param string $arg
     * @return mixed
     */
    protected function _applyFilters($arg)
    {
        // if there is no arg but there is a default return that
        if (!$arg && $this->_default !== null) {
            return $this->_default;
        }

        if (!$arg) {
            return null;
        }

        // trim
        if ($this->_trim && !is_object($arg) && !is_array($arg)) {
            $arg = trim($arg);
        }

        // strip htmls
        if ($this->_strip_html && !is_object($arg) && !is_array($arg)) {
            $arg = strip_tags($arg);
        }

        // apply user functions
        foreach ($this->_functions as $function) {
            $arg = call_user_func($function, $arg);
        }

        // if there are options
        if ($this->_options !== null && !in_array($arg, $this->_options)) {
            $arg = null;
        }

        // cast the var to the specified type
        switch ($this->_type) {
            case 'string':
                $arg = (string) $arg;
                break;
            case 'array':
                $arg = (array) $arg;
                break;
            case 'int':
                $arg = (int) $arg;
                break;
            case 'bool':
                $arg = strtolower($arg);
                switch ($arg) {
                    case '1':
                    case 'true':
                        $arg = true;
                        break;
                    case '0':
                    case 'false':
                        $arg = false;
                        break;
                }
                $arg = (bool) $arg;
                break;
            case 'hex':
                if (!ctype_xdigit($arg)) {
                    return null;
                }
                break;
        }

        // @todo remove this
        // can't think of any way to get this code to run
        // if (!$arg && $this->_default !== null) {
            // return $this->_default;
        // }

        if (!$arg) {
            return null;
        }

        return $arg;
    }
}
