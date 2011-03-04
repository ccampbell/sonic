<?php
namespace Sonic;

/**
 * Layout - it's a view that holds another view
 *
 * @category Sonic
 * @package Layout
 * @author Craig Campbell
 */
class Layout extends View
{
    /**
     * @var string
     */
    const MAIN = 'main';

    /**
     * @var string
     */
    const TOP_VIEW = 'top_view';

    /**
     * @var string
     */
    protected static $_title_pattern;

    /**
     * buffers the top view before outputting the layout
     *
     * @return void
     */
    public function output($json = false, $id = null)
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
        return $this->data(self::TOP_VIEW, $view);
    }

    /**
     * sets title pattern
     *
     * @param string (something like "my application - {{title}}")
     * @return string
     */
    public function setTitlePattern($pattern)
    {
        self::$_title_pattern = $pattern;
        return self::getTitle($this->topView() ? $this->topView()->title() : '');
    }

    /**
     * gets a title for a view
     *
     * @param string
     * @return string
     */
    public static function getTitle($string)
    {
        if (!self::$_title_pattern) {
            return $string;
        }
        return str_replace('${title}', $string, self::$_title_pattern);
    }

    /**
     * gets the url for no turbo
     *
     * @return string
     */
    public function noTurboUrl()
    {
        $uri = $_SERVER['REQUEST_URI'];
        if (strpos($uri, '?') !== false) {
            return $uri . '&noturbo=1';
        }
        return $uri . '?noturbo=1';
    }

    /**
     * outputs the turbo json
     *
     * @return string
     */
    public function turbo()
    {
        // fixes weird issue where some servers won't flush the output
        while (ob_get_level()) {
            ob_end_flush();
        }
        flush();
        return App::getInstance()->processViewQueue();
    }
}
