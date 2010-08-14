<?php
namespace Sonic;

/**
 * View
 *
 * @package Sonic
 * @subpackage View
 * @author Craig Campbell
 */
class View
{
    /**
     * name of current controller
     *
     * @var string
     */
    protected $_active_controller;

    /**
     * @var string
     */
    protected $_path;

    /**
     * @var string
     */
    protected $_title;

    /**
     * @var string
     */
    protected $_html;

    /**
     * @var bool
     */
    protected $_disabled = false;

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
     * sets or gets path
     *
     * @param mixed $path
     * @return string
     */
    public function path($path = null)
    {
        if ($path !== null) {
            $this->_path = $path;
        }
        return $this->_path;
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
            $this->_title = $title;
        }
        return $this->_title;
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
            $controller = $this->_active_controller;
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

        ob_start();
        $this->output();
        $this->_html = ob_get_contents();
        ob_end_clean();
    }

    /**
     * gets html for this buffered view
     *
     * @return string
     */
    public function getHtml()
    {
        return $this->_html;
    }

    /**
     * outputs this view to the page
     *
     * @return void
     */
    public function output()
    {
        if ($this->_disabled) {
            return;
        }

        if ($this->getHtml() !== null) {
            echo $this->getHtml();
            return;
        }
        include $this->_path;
    }
}
