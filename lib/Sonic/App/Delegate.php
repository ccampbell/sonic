<?php
namespace Sonic\App;
use Sonic\App, Sonic\Controller, Sonic\View, Sonic\Layout;

/**
 * App Delegate Interface
 *
 * @package App
 * @subpackage Delegate
 * @author Craig Campbell
 */
abstract class Delegate
{
    /**
     * instance of the application
     *
     * @param \Sonic\App
     */
    protected $_app;

    /**
     * sets the sonic application instance for this delegate
     *
     * @param App $app
     * @return void
     */
    public final function setApp(App $app)
    {
        $this->_app = $app;
    }

    /**
     * called when the application first starts loading before any files are included
     *
     * all application settings should be set here
     *
     * @param string $mode startup mode of application
     * @return void
     */
    public function appStartedLoading($mode) {}

    /**
     * called when all the core files are done being included for the request
     *
     * all extra files you need to include for every request should happen here
     *
     * @return void
     */
    public function appFinishedLoading() {}

    /**
     * called when the routes are done being processed and the app starts to run the first action
     *
     * @return void
     */
    public function appStartedRunning() {}

    /**
     * final thing called when the application has finished running
     *
     * @return void
     */
    public function appFinishedRunning() {}

    /**
     * fired when an action was called by the application for the first time
     * in turbo mode this action will not run immediately
     *
     * @param Controller $controller
     * @param string $action name of action
     * @return void
     */
    public function actionWasCalled(Controller $controller, $action) {}

    /**
     * called when an action has started running
     *
     * @param Controller $controller
     * @param string $action name of action
     * @return void
     */
    public function actionStartedRunning(Controller $controller, $action) {}

    /**
     * called when an action has finished running
     *
     * @param Controller $controller
     * @param string $action name of action
     * @return void
     */
    public function actionFinishedRunning(Controller $controller, $action) {}

    /**
     * called when the layout starts rendering
     *
     * @param Layout $layout
     * @return void
     */
    public function layoutStartedRendering(Layout $layout) {}

    /**
     * called when the layout finishes rendering
     *
     * @param Layout $layout
     * @return void
     */
    public function layoutFinishedRendering(Layout $layout) {}

    /**
     * called when a view starts rendering
     *
     * @param View $view
     * @return void
     */
    public function viewStartedRendering(View $view) {}

    /**
     * called when a view finishes rendering
     *
     * @param View $view
     * @return void
     */
    public function viewFinishedRendering(View $view) {}

    /**
     * called when the application has caught an exception
     *
     * @param \Exception $e
     * @param string $controller name of controller that exception came from
     * @param string $action name of action that exception came from
     * @return void
     */
    public function appCaughtException(\Exception $e, $controller = null, $action = null) {}
}
