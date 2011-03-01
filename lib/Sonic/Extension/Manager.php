<?php
namespace Sonic\Extension;
use Sonic\App;

/**
 * Manager
 *
 * @category Sonic
 * @package Extension
 * @author Craig Campbell
 */
class Manager
{
    const INSTALL = 'install';
    const UNINSTALL = 'uninstall';
    const UPGRADE = 'upgrade';
    const RELOAD = 'reload';
    const LOCAL = '--local';
    const FORCE = '--force';
    const VERBOSE = '--verbose';

    /**
     * @var Manager
     */
    protected static $_instance;

    /**
     * @var array
     */
    protected $_trackers = array();

    /**
     * @var bool
     */
    protected $_verbose = false;

    /**
     * constructor
     *
     * @return void
     */
    private function __construct()
    {
        App::includeFile('Sonic/Extension/Exception.php');
        App::includeFile('Sonic/Extension/Manifest.php');
        App::includeFile('Sonic/Extension/Tracker.php');
    }

    /**
     * gets manager instance
     *
     * @return Manager
     */
    public static function getInstance()
    {
        if (self::$_instance === null) {
            self::$_instance = new Manager();
        }
        return self::$_instance;
    }

    /**
     * gets a tracker by name
     *
     * @param string $name name of extension
     */
    public function getTracker($name)
    {
        if (!isset($this->_trackers[$name])) {
            $this->_trackers[$name] = new Tracker();
        }
        return $this->_trackers[$name];
    }

    /**
     * starts extension manager
     *
     * @param array $args
     */
    public static function start($args = array())
    {
        $manager = self::getInstance();

        if (count($args) < 3) {
            throw new Exception("invalid arguments\n" . $manager->showUsage());
        }

        if (in_array('-h', $args) || in_array('--help', $args)) {
            echo $manager->showUsage();
            exit;
        }

        $action = $args[1];
        $local = in_array(self::LOCAL, $args);
        $force = in_array(self::FORCE, $args);
        $manager->_verbose = in_array(self::VERBOSE, $args);

        $path = array_pop($args);
        while (in_array($path, array(self::FORCE, self::LOCAL, self::VERBOSE))) {
            $path = array_pop($args);
        }

        $path = rtrim($path, DIRECTORY_SEPARATOR);

        switch ($action) {
            case self::UPGRADE:
            case self::INSTALL:
                $manager->install($path, $local);
                break;
            case self::RELOAD:
                $force = true;
                $manager->install($path, $local, $force);
                break;
            case self::UNINSTALL:
                $manager->uninstall($path, $force);
                break;
            default:
                throw new Exception("invalid action specified\n" . $manager->showUsage());
                break;
        }
    }

    /**
     * installs the extension
     *
     * @param string $name
     * @param bool $local
     * @return void
     */
    public function install($name, $local = false, $force = false)
    {
        $name = $this->_lowercaseEnd($name);
        $this->_output('installing ' . $name, true);
        if ($local) {
            return $this->_localInstall($name, $force);
        }
        return $this->_remoteInstall($name, $force);
    }

    /**
     * uninstalls extension with given name
     *
     * @param string $name
     * @param bool $force
     * @return void
     */
    public function uninstall($name, $force = false)
    {
        $lc_name = strtolower($name);
        $data = $this->_getInstallationData();
        if (!isset($data[$lc_name])) {
            throw new Exception('extension: ' . $name . ' is not installed');
        }

        // find extensions that depend on this one and will break if this one
        // is uninstalled
        $will_break = array();
        foreach ($data as $ext => $info) {
            if (!isset($info['dependencies'])) {
                continue;
            }

            if (in_array($lc_name, $info['dependencies'])) {
                $will_break[] = $ext;
            }
        }

        if (!$force && count($will_break)) {
            throw new Exception(implode(', ', $will_break) . ' ' . (count($will_break) == 1 ? 'depends' : 'depend') . ' on ' . $name . '.  use --force to uninstall anyway.');
        }

        // force uninstall
        $base_path = App::getInstance()->getPath() . '/';
        foreach ($data[$lc_name]['files'] as $path) {
            unlink($base_path . $path);
            $this->_output('removed file ' . $path, true);
        }

        foreach ($data[$lc_name]['dirs'] as $dir) {

            // ignore warnings cause that means files were added after this directory
            // was created by the extension which means we don't want to remove the directory
            @rmdir($base_path . $dir);
            $this->_output('removed dir ' . $dir, true);
        }

        foreach ($data[$lc_name]['moved'] as $moved) {
            $this->_output('restored backup from ' . $moved . '.backup', true);
            rename($base_path . $moved . '.backup', $base_path . $moved);
        }

        $extension_dir = App::getInstance()->getPath('extensions') . '/' . $name;
        if (is_dir($extension_dir)) {
            exec('rm -r ' . $extension_dir);
        }

        unset($data[$lc_name]);
        $this->_saveInstallationData($data);
        $this->_output('extension: ' . $name . ' uninstalled successfully');
    }

    /**
     * strips the filename off the path and returns the whole path with it lowercase
     *
     * @param string $path
     * @return string
     */
    protected function _lowercaseEnd($path)
    {
        $path_bits = explode(DIRECTORY_SEPARATOR, $path);
        $file = strtolower(array_pop($path_bits));
        $path = implode(DIRECTORY_SEPARATOR, $path_bits) . DIRECTORY_SEPARATOR . $file;
        return $path;
    }

    /**
     * local installation of extension by name
     *
     * @param string $name
     * @return void
     */
    protected function _localInstall($path, $force = false)
    {
        if (!file_exists($path)) {
            throw new Exception('extension not found at path: ' . $path);
        }

        $path_bits = explode(DIRECTORY_SEPARATOR, $path);
        $name = array_pop($path_bits);
        $base_path = implode(DIRECTORY_SEPARATOR, $path_bits);

        $manifest_path = $path . '/_manifest.php';
        if (!file_exists($manifest_path)) {
            throw new Exception('extension is missing manifest class: ' . $manifest_path);
        }

        App::includeFile($manifest_path);
        $class = $name . 'Manifest';
        $manifest = new $class;

        if (!$manifest instanceof Manifest) {
            throw new Exception('manifest file for ' . $name . ' must extend Sonic\Extension\Manifest');
        }

        foreach ($manifest->getDependencies() as $dependency) {
            $this->_output('processing dependency: ' . $dependency, true);
            $this->install($base_path . '/' . $dependency, true, $force);
        }

        $data = $this->_getInstallationData();

        if (isset($data[$name]['version']) && $data[$name]['version'] >= $manifest::VERSION && !$force) {
            $this->_output('installed version of ' . $name . ' (' . $data[$name]['version'] . ') is greater than or equal to ' . $manifest::VERSION,"\n");
            return;
        }

        // time to actually install this extension
        $already_installed = isset($data[$name]);
        if ($already_installed) {
            unset($data[$name]);
        }

        $extension_dir = App::getInstance()->getPath('extensions') . '/' . $name;
        if (is_dir($extension_dir)) {
            exec('rm -r ' . $extension_dir);
        }

        mkdir($extension_dir);

        $installed = $this->_sync($path, $extension_dir, $already_installed, $name);

        $data[$name] = array();
        $data[$name]['version'] = $manifest::VERSION;
        $data[$name]['files'] = $this->getTracker($name)->getFiles();
        $data[$name]['dirs'] = $this->getTracker($name)->getDirs();
        $data[$name]['moved'] = $this->getTracker($name)->getMoved();
        $data[$name]['dependencies'] = $manifest->getDependencies();

        $this->_saveInstallationData($data);
        $this->_output('extension: ' . $name . ' installed successfully');
    }

    /**
     * syncs directory 1 with directory 2
     *
     * @var string $dir1 path to directory we are installing from
     * @var string $dir2 path to directory we are installing to
     * @var bool $installed is this extension already installed?
     * @var string $ext_name name of extension
     */
    protected function _sync($dir1, $dir2, $installed = false, $ext_name)
    {
        $files = new \RecursiveDirectoryIterator($dir1);
        foreach ($files as $file) {
            $name = $file->getFilename();

            // ignore any files beginning with . or _
            if (in_array($name[0], array('.', '_'))) {
                continue;
            }

            // if this is a directory used by the app then copy the files into
            // that directory
            $app_dirs = array('configs', 'controllers', 'public_html', 'util', 'views');
            if ($file->isDir() && in_array($file->getFilename(), $app_dirs)) {
                $this->_sync($file->getPathname(), App::getInstance()->getPath($file->getFilename()), $installed, $ext_name);
                continue;
            }

            $old_path = $file->getPath() . '/' . $name;

            $new_name = str_replace($dir1 . DIRECTORY_SEPARATOR, '', $old_path);
            $new_path = $dir2 . '/' . $new_name;

            $this->_createDirectoriesFor($dir2, $new_name, $ext_name);

            if ($file->isDir()) {
                $this->_sync($old_path, $new_path, $installed, $ext_name);
                continue;
            }

            if (!$installed && file_exists($new_path)) {
                $this->_output('WARNING: file already exists at path: ' . $new_path . '! it has been backed up for you.');
                copy($new_path, $new_path . '.backup');
                $this->getTracker($ext_name)->moved($this->_stripApp($new_path));
            }

            copy($old_path, $new_path);
            $this->_output('copied ' . $old_path . ' to ' . $new_path, true);
            $this->getTracker($ext_name)->addedFile($this->_stripApp($new_path));
        }
    }

    /**
     * creates missing directories for installing files
     *
     * @param string $dir path to base directory
     * @param string $path path to file we want to create in that directory
     * @return string $ext_name name of extension
     */
    protected function _createDirectoriesFor($dir, $path, $ext_name)
    {
        $dirs = array();

        if (!is_dir($dir)) {
            mkdir($dir);
            $this->_output('creating directory ' . $dir, true);
            $this->getTracker($ext_name)->addedDir($this->_stripApp($dir));
        }

        $dir = $dir . '/';
        $path_bits = explode(DIRECTORY_SEPARATOR, $path);
        $file = array_pop($path_bits);
        $prev_bit = '';
        foreach ($path_bits as $bit) {
            $dir_path = $dir . $prev_bit . $bit;
            if (!is_dir($dir_path)) {
                mkdir($dir_path);
                $this->_output('creating directory ' . $dir_path, true);
                $this->getTracker($ext_name)->addedDir($this->_stripApp($dir_path));
            }
            $prev_bit = $bit . '/';
        }
    }

    /**
     * strips the app path from the file path
     *
     * @param string $path
     * @return string
     */
    protected function _stripApp($path)
    {
        return str_replace(App::getInstance()->getPath() . DIRECTORY_SEPARATOR, '', $path);
    }

    /**
     * does a remote installation by name
     *
     * @param $name
     * @return void
     */
    protected function _remoteInstall($name, $force = false)
    {
        throw new Exception('remote installation coming soon!');
    }

    /**
     * outputs a message to command line
     *
     * @param string $message
     * @param bool $verbose_only
     */
    protected function _output($message, $verbose_only = false)
    {
        if ($verbose_only && !$this->_verbose) {
            return;
        }
        echo $message,"\n";
    }

    /**
     * gets the path to the installation json file
     *
     * @return string
     */
    protected function _getInstallationDataPath()
    {
        $extensions_path = App::getInstance()->getPath('extensions');

        if (!is_dir($extensions_path)) {
            mkdir($extensions_path);
        }

        return $extensions_path . '/installed.json';
    }

    /**
     * gets array of installation data from installed.json file
     *
     * @return array
     */
    protected function _getInstallationData()
    {
        $path = $this->_getInstallationDataPath();
        if (!file_exists($path)) {
            $this->_saveInstallationData(array());
            return array();
        }

        return json_decode(file_get_contents($path), true);
    }

    /**
     * saves array of installation data to installed.json
     *
     * @param array
     * @return mixed
     */
    protected function _saveInstallationData($data)
    {
        $json = json_encode($data);
        $path = $this->_getInstallationDataPath();
        file_put_contents($path, $json);
    }

    /**
     * shows usage
     *
     * @return string
     */
    public function showUsage()
    {
        $usage = "./util/extension.php (install|upgrade|uninstall|reload) NameOfExtension\n\n";
        $usage .= "--help           shows this menu\n";
        $usage .= "--force          forces the extension to install/uninstall\n";
        $usage .= "--verbose        shows verbose output\n";

        return $usage;
    }
}
