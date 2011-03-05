<?php
namespace Sonic\Cache;
use Sonic\Util;

/**
 * File
 *
 * @todo add cleanup so on x percent of requests expired keys are removed
 * @category Sonic
 * @package Cache
 * @author Craig Campbell
 */
class File
{
    /**
     * @var string
     */
    protected $_dir;

    /**
     * @var array
     */
    protected $_caches = array();

    /**
     * constructor
     */
    public function __construct($dir)
    {
        if (!is_dir($dir)) {
            throw new \Sonic\Exception('directory does not exist at path: ' . $dir);
        }

        \Sonic\App::getInstance()->includeFile('Sonic/Util.php');
        $this->_dir = rtrim($dir, '/');
    }

    /**
     * sets a key to cache
     *
     * @param string $key
     * @param mixed $value
     * @param mixed
     */
    public function set($key, $value, $expiration = '1 hour')
    {
        $path = $this->_pathForKey($key);
        $expiration = $_SERVER['REQUEST_TIME'] + Util::toSeconds($expiration);
        $data = array(
            'expiration' => $expiration,
            'created'=> $_SERVER['REQUEST_TIME'],
            'value' => $value
        );

        $this->_caches[$key] = $value;

        $string_data = serialize($data);

        return @file_put_contents($path, $string_data);
    }

    /**
     * gets a key from cache
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        if (array_key_exists($key, $this->_caches)) {
            return $this->_caches[$key];
        }

        $path = $this->_pathForKey($key);
        if (!file_exists($path)) {
            return false;
        }

        $contents = unserialize(file_get_contents($path));
        $time = $_SERVER['REQUEST_TIME'];

        if ($time >= $contents['expiration']) {
            unlink($path);
            return false;
        }

        $this->_caches[$key] = $contents['value'];

        return $contents['value'];
    }

    /**
     * deletes a key from cache
     *
     * @param string $key
     */
    public function delete($key)
    {
        $path = $this->_pathForKey($key);

        if (isset($this->_caches[$key])) {
            unset($this->_caches[$key]);
        }

        if (file_exists($path)) {
            return unlink($path);
        }
    }

    /**
     * gets path for key
     *
     * @param string $key
     * @return string
     */
    protected function _pathForKey($key)
    {
       return $this->_dir . '/' . $key . '.txt';
    }
}
