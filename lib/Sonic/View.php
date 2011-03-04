<?php
namespace Sonic;

/**
 * View
 *
 * @category Sonic
 * @package View
 * @author Craig Campbell
 */
class View
{
    /**
     * @var string
     */
    const TITLE = 1;

    /**
     * @var string
     */
    const CSS = 2;

    /**
     * @var string
     */
    const JS = 3;

    /**
     * @var string
     */
    const PATH = 4;

    /**
     * @var string
     */
    const TURBO_PLACEHOLDER = 5;

    /**
     * @var string
     */
    const KEYWORDS = 6;

    /**
     * @var string
     */
    const DESCRIPTION = 7;

    /**
     * name of current controller
     *
     * @var string
     */
    protected $_active_controller;

    /**
     * name of current action
     *
     * @var string
     */
    protected $_action;

    /**
     * @var array
     */
    protected $_data = array(
        self::JS => array(),
        self::CSS => array()
    );

    /**
     * @var string
     */
    protected $_html;

    /**
     * @var bool
     */
    protected $_disabled = false;

    /**
     * @var array
     */
    protected $_turbo_data = array();

    /**
     * @var array
     */
    protected static $_static_path = '/assets';

    /**
     * constructor
     *
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path($path);
    }

    /**
     * gets a view variable
     *
     * @param string $var
     * @return string
     */
    public function __get($var)
    {
        if (!isset($this->$var)) {
            return null;
        }
        return $this->$var;
    }

    /**
     * escapes a view variable
     *
     * @param string
     * @return string
     */
    public function escape($string)
    {
        return htmlentities($string, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * sets or gets static path to js and css
     *
     * @param string $path
     * @return string
     */
    public static function staticPath($path = null)
    {
        if (!$path) {
            return self::$_static_path;
        }
        self::$_static_path = $path;
        return self::$_static_path;
    }

    /**
     * sets or gets data related to this view
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function data($key, $value = null)
    {
        $data = isset($this->_data[$key]) ? $this->_data[$key] : null;

        if ($value === null) {
            return $data;
        }

        if (is_array($data)) {
            $this->_data[$key][] = $value;
            return;
        }

        $this->_data[$key] = $value;
    }

    /**
     * sets or gets path
     *
     * @param mixed $path
     * @return string
     */
    public function path($path = null)
    {
        return $this->data(self::PATH, $path);
    }

    /**
     * sets or gets title
     *
     * @param mixed $title
     * @return string
     */
    public function title($title = null)
    {
        if ($title !== null) {
            $title = Layout::getTitle($title);
        }
        return $this->data(self::TITLE, $title);
    }

    /**
     * sets or gets keywords
     *
     * @param string $keywords
     * @return string
     */
    public function keywords($keywords = null)
    {
        return $this->data(self::KEYWORDS, $keywords);
    }

    /**
     * sets or gets description
     *
     * @param string $description
     * @return string
     */
    public function description($description = null)
    {
        return $this->data(self::DESCRIPTION, $description);
    }

    /**
     * appends variables to this view
     *
     * @param array
     * @return void
     */
    public function addVars(array $args)
    {
        foreach ($args as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * are we in turbo mode?
     *
     * @return bool
     */
    public function isTurbo()
    {
        return App::getInstance()->getSetting(App::TURBO);
    }

    /**
     * sets the active controller for this view
     *
     * @param string $name
     * @return void
     */
    public function setActiveController($name)
    {
        $this->_active_controller = $name;
    }

    /**
     * sets action for this view
     *
     * @param string $action
     * @return void
     */
    public function setAction($name)
    {
        $this->_action = $name;
    }

    /**
     * adds javascript file for inclusion
     *
     * @param string
     * @return void
     */
    public function addJs($path)
    {
        if ($this->_isAbsolute($path)) {
            return $this->data(self::JS, $path);
        }
        $this->data(self::JS, $this->staticPath() . '/js/' . $path);
    }

    /**
     * adds css file for inclusion
     *
     * @param string
     * @return void
     */
    public function addCss($path)
    {
        if ($this->_isAbsolute($path)) {
            return $this->data(self::CSS, $path);
        }
        $this->data(self::CSS, $this->staticPath() . '/css/' . $path);
    }

    /**
     * determines if this is an absolute path
     *
     * @param string $path
     * @return bool
     */
    protected function _isAbsolute($path)
    {
        // if there isn't enough characters to begin with http://
        if (!isset($path[7])) {
            return false;
        }
        return $path[0] . $path[1] . $path[2] . $path[3] . $path[4] == 'http:';
    }

    /**
     * gets js files included
     *
     * @return array
     */
    public function getJs()
    {
        return $this->data(self::JS);
    }

    /**
     * gets css files included
     *
     * @return array
     */
    public function getCss()
    {
        return $this->data(self::CSS);
    }

    /**
     * disables this view
     *
     * @return void
     */
    public function disable()
    {
        $this->_disabled = true;
    }

    /**
     * renders this view using the controller and action specified
     *
     * @see App::runController()
     * @param string
     * @param string
     * @param array
     * @return void
     */
    public function render($controller, $action = null, $args = array())
    {
        if ($action === null || is_array($action)) {
            $args = (array) $action;
            $action = $controller;
            $controller = $this instanceof Layout ? Layout::MAIN : $this->_active_controller;
        }

        App::getInstance()->runController($controller, $action, $args);
    }

    /**
     * buffers this view so HTML can be pulled out at a later time
     * currently used for the top most view in a layout to pull out CSS and JS and page title
     *
     * @return void
     */
    public function buffer()
    {
        if ($this->_disabled) {
            return;
        }

        if ($this->isTurbo()) {
            return;
        }

        ob_start();
        $this->output();
        $this->_html = ob_get_contents();
        ob_end_clean();
    }

    /**
     * gets or sets placeholder markup/text for using turbo mode
     * for example if you want to display "loading..." or a loading gif
     *
     * @param string
     * @return void
     */
    public function turboPlaceholder($html = null)
    {
        return $this->data(self::TURBO_PLACEHOLDER, $html);
    }

    /**
     * generates a unique id based on the current view
     *
     * @param string $controller
     * @param string $action
     * @return string
     */
    public static function generateId($controller, $action)
    {
        return 'v' . substr(md5($controller . '::' . $action), 0, 7);
    }

    /**
     * gets an id for the current view
     *
     * @return string
     */
    public function getId()
    {
        return $this->generateId($this->_active_controller, $this->_action);
    }

    /**
     * gets html for this buffered view
     *
     * @return string
     */
    public function getHtml()
    {
        if ($this->isTurbo() && !$this instanceof Layout && !$this->_html) {
            App::getInstance()->queueView($this->_active_controller, $this->_action);
            $placeholder = $this->data(self::TURBO_PLACEHOLDER) ?: App::getInstance()->getSetting(App::TURBO_PLACEHOLDER);
            $this->_html = '<div class="sonic_fragment" id="' . $this->getId() . '">' . $placeholder . '</div>';
        }
        return $this->_html;
    }

    /**
     * adds additional turbo data such as cookies to set or pages to redirect to
     *
     * @param string
     * @return void
     */
    public function addTurboData($key, $value)
    {
        $this->_turbo_data[$key] = $value;
    }

    /**
     * outputs the view as json
     *
     * @param mixed $id id of view to output to (this is so exceptions can output into the view that caused them to begin with)
     * @return string
     */
    public function outputAsJson($id = null)
    {
        if (!$id) {
            $id = $this->getId();
        }

        ob_start();
        include $this->data(self::PATH);
        $html = ob_get_contents();
        ob_end_clean();

        $data = array(
            'id' => $id,
            'content' => $html,
            'title' => $this->title(),
            'css' => $this->getCss(),
            'js' => $this->getJs()) + $this->_turbo_data;

        $output = '<script>SonicTurbo.render(' . json_encode($data) . ');</script>';
        return $output;
    }

    /**
     * outputs this view to the page
     *
     * @param bool $json should we output json for turbo mode?
     * @param mixed $id should we use a specific view for turbo mode
     * @return void
     */
    public function output($json = false, $id = null)
    {
        if ($this->_disabled) {
            return;
        }

        App::getInstance()->outputStarted(true);

        if (!$json && !$this instanceof Layout && $this->getHtml() !== null) {
            echo $this->getHtml();
            return;
        }

        if ($json) {
            echo $this->outputAsJson($id);
            return;
        }

        include $this->data(self::PATH);
    }

    /**
     * return the class as the action name
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->_action;
    }
}
