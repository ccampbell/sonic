<?php
namespace Sonic;

/**
 * Controller
 *
 * @category Sonic
 * @package Controller
 * @author Craig Campbell
 */
class Controller
{
    /**
     * @var string
     */
    protected $_name;

    /**
     * @var string
     */
    protected $_view_name;

    /**
     * @var View
     */
    protected $_view;

    /**
     * @var Layout
     */
    protected $_layout;

    /**
     * @var string
     */
    protected $_layout_name = Layout::MAIN;

    /**
     * @var Request
     */
    protected $_request;

    /**
     * @var array
     */
    protected $_actions_completed = array();

    /**
     * @var InputFilter
     */
    protected $_input_filter;

    /**
     * magic getter for accessing view or layout
     *
     * @param string
     * @return Object
     */
    public function __get($var)
    {
        if ($var === 'view') {
            return $this->getView();
        }

        if ($var === 'layout') {
            return $this->getLayout();
        }

        throw new Exception('only views and layouts are magic');
    }

    /**
     * gets or sets the name of the controller
     *
     * @param mixed $name
     * @return string
     */
    final public function name($name = null)
    {
        if ($name !== null) {
            $this->_name = $name;
        }
        return $this->_name;
    }

    /**
     * sets the view for this controller run
     *
     * @param string $name name of view to render
     * @param bool $from_controller was this called from a controller
     * @return Controller
     */
    final public function setView($name, $from_controller = true)
    {
        // setting the view to the view we are already on
        if ($this->_view_name == $name) {
            return $this;
        }

        $this->_view_name = $name;

        // if we are setting the view from a controller that means
        // all we should do is update the path
        if ($from_controller) {
            $this->getView()->path($this->getViewPath());
        }

        // if the view is being set from the application then that means
        // the view needs to get reset to prevent it from being cached
        if (!$from_controller) {
            $this->_view = null;
        }

        // if the layout is null that means it was disabled
        if ($this->_layout_name === null) {
            return $this;
        }

        return $this;
    }

    /**
     * gets or sets the request object
     *
     * @param mixed $request (Request || null)
     * @return Request
     */
    public function request(Request $request = null)
    {
        if ($request !== null) {
            $this->_request = $request;
        }
        return $this->_request;
    }

    /**
     * marks an action as complete once it runs
     *
     * @param string $action name of action
     * @return Controller
     */
    public function actionComplete($action)
    {
        $this->_actions_completed[$action] = true;
        return $this;
    }

    /**
     * gets an array of all actions this controller has completed
     *
     * @return array
     */
    public function getActionsCompleted()
    {
        return array_keys($this->_actions_completed);
    }

    /**
     * checks if a specific action has been completed already
     *
     * @param string $action
     * @return bool
     */
    public function hasCompleted($action)
    {
        return isset($this->_actions_completed[$action]);
    }

    /**
     * disables the layout
     *
     * return Controller
     */
    public function disableLayout()
    {
        $this->_layout_name = null;
        return $this;
    }

    /**
     * disables the view for this action
     *
     * @return void
     */
    public function disableView()
    {
        $this->getView()->disable();
    }

    /**
     * determines if this controller has a layout
     *
     * @return bool
     */
    public function hasLayout()
    {
        return $this->_layout_name !== null;
    }

    /**
     * sets layout
     *
     * @param string name of layout
     * @return void
     */
    public function setLayout($name)
    {
        $this->_layout_name = $name;
    }

    /**
     * gets layout
     *
     * @return Layout
     */
    public function getLayout()
    {
        if ($this->_layout === null) {
            $layout_dir = App::getInstance()->getPath('views/layouts');
            $layout = new Layout($layout_dir . '/' . $this->_layout_name . '.phtml');
            $this->_layout = $layout;
        }

        return $this->_layout;
    }

    /**
     * gets the view path based on the view name
     *
     * @return string
     */
    final public function getViewPath()
    {
        return App::getInstance()->getPath('views') . '/' . $this->_name . '/' . $this->_view_name . '.phtml';
    }

    /**
     * gets the view object
     *
     * @return View
     */
    public function getView()
    {
        if ($this->_view === null) {
            $this->_view = new View($this->getViewPath());
            $this->_view->setAction($this->_view_name);
            $this->_view->setActiveController($this->_name);
        }

        return $this->_view;
    }

    /**
     * redirects to another page
     *
     * @param string uri
     * @return void
     */
    protected function _redirect($location)
    {
        if (App::getInstance()->getSetting(App::TURBO)) {
            $this->getView()->addTurboData('redirect', $location);
            return;
        }
        header('location: ' . $location);
        exit;
    }

    /**
     * sends back json response
     *
     * @param array
     * @return json
     */
    protected function _json(array $data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * returns a filter object to use for this
     *
     * @param string $name
     * @return InputFilter
     */
    public function filter($name)
    {
        if ($this->_input_filter !== null) {
            return $this->_input_filter->filter($name);
        }

        App::getInstance()->includeFile('Sonic/InputFilter.php');
        $this->_input_filter = new InputFilter($this->request());
        return $this->_input_filter->filter($name);
    }

    /**
     * returns this class as a string
     *
     * @return string
     */
    public function __toString()
    {
        return get_class($this);
    }
}
