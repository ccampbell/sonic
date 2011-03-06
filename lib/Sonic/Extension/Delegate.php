<?php
namespace Sonic\Extension;
use Sonic\App, Sonic\Controller;

/**
 * Extension Delegate Interface
 *
 * @category Sonic
 * @package Extension
 * @author Craig Campbell
 */
abstract class Delegate
{
    /**
     * called when an extension starts loading
     *
     * @return void
     */
    public function extensionStartedLoading() {}

    /**
     * called when an extension loaded a file
     *
     * @return void
     */
    public function extensionLoadedFile($filename) {}

    /**
     * called when an extension is finished loading
     *
     * @return void
     */
    public function extensionFinishedLoading() {}

    /**
     * adds a controller method
     *
     * @param string $name
     * @param Closure $function
     */
    protected function _addControllerMethod($name, $function)
    {
        Controller::getTransformation()->addMethod($name, $function);
    }

    /**
     * adds a static controller method
     *
     * @param string $name
     * @param Closure $function
     */
    protected function _addStaticControllerMethod($name, $function)
    {
        Controller::getTransformation()->addStaticMethod($name, $function);
    }

    /**
     * adds an app method
     *
     * @param string $name
     * @param Closure $function
     */
    protected function _addAppMethod($name, $function)
    {
        App::getTransformation()->addMethod($name, $function);
    }

    /**
     * adds a static app method
     *
     * @param string $name
     * @param Closure $function
     */
    protected function _addStaticAppMethod($name, $function)
    {
        App::getTransformation()->addStaticMethod($name, $function);
    }
}
