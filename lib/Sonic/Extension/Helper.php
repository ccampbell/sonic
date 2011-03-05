<?php
namespace Sonic\Extension;
use Sonic\App;

/**
 * Helper
 *
 * @category Sonic
 * @package Extension
 * @author Craig Campbell
 */
class Helper
{
    /**
     * @var array
     */
    protected static $_helpers = array();

    /**
     * @var string
     */
    protected $_name;

    /**
     * constructor
     */
    public function __construct($name)
    {
        $name = strtolower($name);
        if (!App::getInstance()->extensionLoaded($name)) {
            $this->_raiseException('extension "' . $name . '" is not loaded!');
        }
        $this->_name = $name;
    }

    /**
     * loads a helper by extension name
     *
     * @param string $name
     * @return Helper
     */
    public static function forExtension($name)
    {
        if (!isset(self::$_helpers[$name])) {
            self::$_helpers[$name] = new Helper($name);
        }
        return self::$_helpers[$name];
    }

    /**
     * gets extension version
     *
     * @return string
     */
    public function getVersion()
    {
        $data = $this->getData();
        return $data['version'];
    }

    /**
     * gets installed files
     *
     * @return array
     */
    public function getInstalledFiles()
    {
        $data = $this->getData();
        return $data['files'];
    }

    /**
     * gets dependencies
     *
     * @return array
     */
    public function getDependencies()
    {
        $data = $this->getData();
        return $data['dependencies'];
    }

    /**
     * gets a config for this extension
     *
     * @return Sonic\Config
     */
    public function getConfig()
    {
        $data = $this->getData();
        if (!isset($data['config'])) {
            return null;
        }

        $path = App::getInstance()->getPath($data['config']);
        return App::getConfig($path);
    }

    /**
     * gets data related to this extension
     *
     * @return array()
     */
    public function getData()
    {
        $data = App::getInstance()->getSetting(App::EXTENSION_DATA);
        if (isset($data[$this->_name])) {
            return $data[$this->_name];
        }

        $this->_raiseException('no data found for extension "' . $this->_name . '"');
    }

    /**
     * raises an extension exception
     *
     * @return void
     */
    protected function _raiseException($message)
    {
        App::getInstance()->includeFile('Sonic/Extension/Exception.php');
        throw new Exception($message);
    }
}
