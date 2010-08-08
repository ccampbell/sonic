<?php
namespace Sonic;

/**
 * Layout - it's a view that holds another view
 *
 * @package Sonic
 * @subpackage Layout
 * @author Craig Campbell
 */
class Layout extends View
{
    /**
     * @var string
     */
    const MAIN = 'main';

    /**
     * @var View
     */
    protected $_top_view;

    /**
     * buffers the top view before outputting the layout
     *
     * @return void
     */
    public function output()
    {
        if ($this->topView() !== null) {
            $this->topView()->buffer();
        }
        parent::output();
    }

    /**
     * sets or gets the top level view
     *
     * @param mixed $view
     * @return View
     */
    public function topView(View $view = null)
    {
        if ($this->_top_view === null && $view !== null) {
            $this->_top_view = $view;
        }
        return $this->_top_view;
    }
}
