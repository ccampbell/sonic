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
     * magic call for methods added at runtime
     *
     * @param string $name
     * @param array $args
     */
    public function __call($name, $args)
    {
        return App::getInstance()->callIfExists($name, $args, __CLASS__, get_class($this));
    }

    /**
     * magic static call for methods added at run time
     *
     * @param string $name
     * @param array $args
     */
    public static function __callStatic($name, $args)
    {
        return App::getInstance()->callIfExists($name, $args, __CLASS__, get_called_class(), true);
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
     * disables the view for this action
     *
     * @return void
     */
    public function disableView()
    {
        $this->_view_name = null;
    }

    /**
     * disables the layout
     *
     * @return void
     */
    public function disableLayout()
    {
        $this->_layout_name = null;
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
            return;
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
     * gets the request object
     *
     * @return Request
     */
    public function request()
    {
        return App::getInstance()->getRequest();
    }

    /**
     * returns a filter object to use for this
     *
     * @param string $name
     * @return InputFilter
     */
    final public function filter($name)
    {
        if ($this->_input_filter !== null) {
            return $this->_input_filter->filter($name);
        }

        App::getInstance()->includeFile('Sonic/InputFilter.php');
        $this->_input_filter = new InputFilter($this->request());
        return $this->_input_filter->filter($name);
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
     * determines if this controller has a layout
     *
     * @return bool
     */
    public function hasLayout()
    {
        return $this->_layout_name !== null;
    }

    /**
     * determines if this controller has a view
     *
     * @return bool
     */
    public function hasView()
    {
        return $this->_view_name !== null;
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
     * gets the view path based on the view name
     *
     * @return string
     */
    final public function getViewPath()
    {
        return App::getInstance()->getPath('views') . '/' . $this->_name . '/' . $this->_view_name . '.phtml';
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
        $this->request()->setHeader('location', $location);
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
        $this->request()->setHeader('Content-Type', 'application/json');
        echo json_encode($data);
        exit;
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
