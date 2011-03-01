<?php
namespace Sonic\Extension;

/**
 * Tracker
 *
 * @category Sonic
 * @package Extension
 * @author Craig Campbell
 */
class Tracker
{
    /**
     * @var array
     */
    protected $_files = array();

    /**
     * @var array
     */
    protected $_dirs = array();

    /**
     * @var array
     */
    protected $_moved = array();

    /**
     * track a file added
     *
     * @param string
     * @return void
     */
    public function addedFile($file)
    {
        $this->_files[] = $file;
    }

    /**
     * track a directory added
     *
     * @param string
     * @return void
     */
    public function addedDir($dir)
    {
        $this->_dirs[] = $dir;
    }

    /**
     * track a file moved
     *
     * @param string
     * @return void
     */
    public function moved($file)
    {
        $this->_moved[] = $file;
    }

    /**
     * gets list of files added
     *
     * @return array
     */
    public function getFiles()
    {
        return $this->_files;
    }

    /**
     * gets list of directories added
     *
     * @return array
     */
    public function getDirs()
    {
        return $this->_dirs;
    }

    /**
     * gets list of files moved
     *
     * @return array
     */
    public function getMoved()
    {
        return $this->_moved;
    }
}
