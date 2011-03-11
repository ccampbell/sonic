<?php
namespace Sonic\Extension;
use Sonic\App, Sonic\Util;

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
    const LIST_ACTION = 'list';
    const OUTDATED = 'outdated';
    const INSTALLED = 'installed';
    const LOCAL = '--local';
    const FORCE = '--force';
    const VERBOSE = '--verbose';
    const DEV = '--dev';
    const DOWNLOAD_URL = 'http://extensions.sonicframework.com/download';

    /**
     * @todo cache list on disk for a couple hours
     */
    const LIST_URL = 'http://extensions.sonicframework.com/list';

    /**
     * @var Manager
     */
    protected static $_instance;

    /**
     * @var array
     */
    protected $_trackers = array();

    /**
     * list of extensions that started to install to prevent infinite loop
     * when two extensions depend on eachother
     *
     * @var array
     */
    protected $_started = array();

    /**
     * @var bool
     */
    protected $_verbose = false;

    /**
     * @var bool
     */
    protected $_dev = false;

    /**
     * constructor
     *
     * @return void
     */
    private function __construct()
    {
        $app = App::getInstance();
        $app->includeFile('Sonic/Extension/Exception.php');
        $app->includeFile('Sonic/Extension/Manifest.php');
        $app->includeFile('Sonic/Extension/Tracker.php');
        $app->includeFile('Sonic/Util.php');
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
     * starts extension manager
     *
     * @param array $args
     */
    public static function start($args = array())
    {
        $manager = self::getInstance();

        if (in_array('-h', $args) || in_array('--help', $args)) {
            echo $manager->showUsage();
            exit;
        }

        if (in_array(self::LIST_ACTION, $args)) {
            return $manager->listExtensions($args);
        }

        if (count($args) < 3 && !in_array(self::LIST_ACTION, $args)) {
            throw new Exception("invalid arguments\n" . $manager->showUsage());
        }

        $action = $args[1];
        $local = in_array(self::LOCAL, $args);
        $force = in_array(self::FORCE, $args);
        $manager->_verbose = in_array(self::VERBOSE, $args);
        $manager->_dev = in_array(self::DEV, $args);

        $path = array_pop($args);
        while (in_array($path, array(self::FORCE, self::LOCAL, self::VERBOSE, self::DEV))) {
            $path = array_pop($args);
        }

        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $path = $manager->_lowercaseEnd($path);
        $name = $manager->_nameFromPath($path);

        $force = $action == self::RELOAD ? true : $force;

        switch ($action) {
            case self::UPGRADE:
            case self::INSTALL:
            case self::RELOAD:
                $manager->install($path, $local, $force);
                break;
            case self::UNINSTALL:
                $manager->uninstall($name, $force);
                break;
            case self::LIST_ACTION:
                break;
            default:
                throw new Exception("invalid action specified\n" . $manager->showUsage());
                break;
        }

        $tmp_path = $manager->_getTmpPath();
        if (is_dir($tmp_path)) {
            $manager->_output('removing ' . $tmp_path, true);
            Util::removeDir($tmp_path);
        }
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
     * lists extensions
     *
     * @param array $args
     * @return string
     */
    public function listExtensions($args = array())
    {
        if (in_array(self::INSTALLED, $args)) {
            $installed = $this->_getInstalled();
            if (empty($installed)) {
                return $this->_output('you do not have any extensions installed');
            }
            return $this->_outputList($installed);
        }

        if (in_array(self::OUTDATED, $args)) {
            $outdated = $this->_getOutdated();
            if (empty($outdated)) {
                return $this->_output('all extensions are up to date');
            }
            return $this->_outputList($outdated);
        }

        $all = $this->_getAll();
        return $this->_outputList($all);
    }

    /**
     * lists installed extensions
     *
     * @return string
     */
    protected function _getInstalled()
    {
        $path = App::getInstance()->getPath('extensions/installed.json');
        return json_decode(file_get_contents($path), true);
    }

    /**
     * lists outdated extensions
     *
     * @return string
     */
    protected function _getOutdated()
    {
        $all = $this->_getAll();
        $installed = $this->_getInstalled();
        $outdated = array();
        foreach ($installed as $name => $extension) {
            if (isset($all[$name]) && $extension['version'] < $all[$name]['version']) {
                $outdated[$name] = array('version' => $all[$name]['version'] . ' (' . $extension['version'] . ' installed)');
            }
        }
        return $outdated;
    }

    /**
     * lists all extensions
     *
     * @return string
     */
    protected function _getAll()
    {
        $this->_output('getting extension list...');
        $json = file_get_contents(self::LIST_URL);
        return json_decode($json, true);
    }

    /**
     * outputs list of extensions
     *
     * @param array
     * @return string
     */
    protected function _outputList($extensions)
    {
        ksort($extensions);
        $pad = $this->_getStringPad($extensions);
        echo str_pad('NAME', $pad) . 'VERSION',"\n";
        foreach ($extensions as $name => $data) {
            echo str_pad($name, $pad) . $data['version'],"\n";
        }
        echo "\n";
    }

    /**
     * gets string pad value based on extension names
     *
     * @return int
     */
    protected function _getStringPad($extensions = array())
    {
        // do some magic to find the longest name
        $lengths = array();
        foreach ($extensions as $name => $data) {
            $lengths[$name] = strlen($name);
        }

        asort($lengths);
        return array_pop($lengths) + 5;
    }

    /**
     * installs the extension
     *
     * @param string $name
     * @param bool $local
     * @return void
     */
    public function install($path, $local = false, $force = false)
    {
        $sentence = $local ? 'installing from ' : 'installing ';
        $this->_output($sentence . $path, true);
        if ($local) {
            return $this->_localInstall($path, $force);
        }

        if (!class_exists('\ZipArchive')) {
            throw new Exception('ZipArchive class is required for remote installations');
        }

        return $this->_remoteInstall($path, $force);
    }


    /**
     * local installation of extension by name
     *
     * @param string $name
     * @return void
     */
    protected function _localInstall($path, $force = false, $from_remote = false)
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

        App::getInstance()->includeFile($manifest_path);
        $class = "\Sonic\Extension\\" . $name;
        $manifest = new $class;

        if (!$manifest instanceof Manifest) {
            throw new Exception('manifest file for ' . $name . ' must extend Sonic\Extension\Manifest');
        }

        $this->_started[] = $name;

        if (count($manifest->getDependencies())) {
            $this->_output($name . ' requires: ' . implode(',', $manifest->getDependencies()));
        }

        foreach ($manifest->getDependencies() as $dependency) {

            // prevent infinite loop if this depends on an extension that depends on it
            if (in_array($dependency, $this->_started)) {
                continue;
            }

            $this->_output('processing dependency: ' . $dependency, true);
            $local = $from_remote ? false : true;
            $dependency = $from_remote ? $dependency : $base_path . '/' . $dependency;
            $this->install($dependency, $local, $force);
        }

        $data = $this->_getInstallationData();
        if (isset($data[$name]['version']) && $data[$name]['version'] >= $manifest::VERSION && !$force) {
            $this->_output('installed version of ' . $name . ' (' . $data[$name]['version'] . ') is greater than or equal to ' . $manifest::VERSION);
            return;
        }

        // uninstall the current version to remove/reset all files
        // incase the new version has moved files around
        $this->uninstall($name, $force, true);

        // time to actually install this extension
        $already_installed = isset($data[$name]);
        if ($already_installed) {
            unset($data[$name]);
        }

        $extension_dir = App::getInstance()->getPath('extensions') . '/' . $name;

        mkdir($extension_dir);

        $installed = $this->_sync($path, $extension_dir, $already_installed, $name, true);

        // if this extension uses a config then write it out
        $config_path = App::getInstance()->getPath('configs') . '/extension.' . $name . '.ini';
        if ($manifest->hasConfig() && !file_exists($config_path)) {
            $defaults = $manifest->getConfigDefaults();
            $text = $this->_configFromArray($defaults);
            $this->_output('creating config file at ' . $config_path, true);
            file_put_contents($config_path, $text);
        }

        // if the extension has routes than add them
        $routes = $manifest->getRoutes();
        if (count($routes)) {
            $this->_installRoutes($routes, $name);
        }

        $data[$name] = array();
        $data[$name]['version'] = $manifest::VERSION;
        $data[$name]['files'] = $this->getTracker($name)->getFiles();
        $data[$name]['dirs'] = $this->getTracker($name)->getDirs();
        $data[$name]['dev'] = $this->_dev;
        $data[$name]['has_core'] = file_exists($path . '/Core.php');
        $data[$name]['routes'] = count($routes);

        if ($manifest->hasConfig()) {
            $data[$name]['config'] = $this->_stripApp($config_path);
        }

        $delegate = $this->getTracker($name)->getDelegate();
        if ($delegate) {
            $data[$name]['delegate'] = $this->_getDelegateClassName($delegate);
            $data[$name]['delegate_path'] = $delegate;
        }

        $data[$name]['moved'] = $this->getTracker($name)->getMoved();
        $data[$name]['dependencies'] = $manifest->getDependencies();

        $this->_saveInstallationData($data);
        $this->_output('extension: ' . $name . ' installed successfully');

        if ($manifest->getInstructions()) {
            $this->_output($manifest->getInstructions());
        }
    }

    /**
     * does a remote installation by name
     *
     * @param $name
     * @return void
     */
    protected function _remoteInstall($name, $force = false)
    {
        $download_url = self::DOWNLOAD_URL . '/' . $name;
        $this->_output('downloading ' . $name . ' extension from ' . $download_url);
        $tmp_path = $this->_getTmpPath();

        if (!is_dir($tmp_path)) {
            mkdir($tmp_path);
        }

        $file = @file_get_contents($download_url);
        if (!$file) {
            throw new Exception('ERROR: extension not found!');
        }

        $path = $tmp_path . '/' . $name . '.tar.gz';
        file_put_contents($path, $file);

        $this->_output('extracting ' . $name . '.zip', true);

        $zip = new \ZipArchive();
        $zip->open($path);
        $zip->extractTo($tmp_path);
        $zip->close();
        unlink($path);

        $this->_localInstall($tmp_path . '/' . $name, $force, true);
    }

    /**
     * uninstalls extension with given name
     *
     * @param string $name
     * @param bool $force
     * @return void
     */
    public function uninstall($name, $force = false, $reload = false)
    {
        $lc_name = strtolower($name);
        $data = $this->_getInstallationData();

        // if not installed and reload
        if (!isset($data[$lc_name]) && $reload) {
            return;
        }

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

        if (!$force && !$reload && count($will_break)) {
            throw new Exception(implode(', ', $will_break) . ' ' . (count($will_break) == 1 ? 'depends' : 'depend') . ' on ' . $name . '.  use --force to uninstall anyway.');
        }

        // force uninstall
        $base_path = App::getInstance()->getPath() . '/';
        foreach ($data[$lc_name]['files'] as $path) {
            $this->_output('removing file ' . $path, true);
            unlink($base_path . $path);
        }

        if (!$reload && isset($data[$lc_name]['config'])) {
            $path = $data[$lc_name]['config'];
            $this->_output('removing file ' . $path, true);
            unlink($base_path . $path);
        }

        foreach ($data[$lc_name]['dirs'] as $dir) {

            // ignore warnings cause that means files were added after this directory
            // was created by the extension which means we don't want to remove the directory
            $this->_output('removing dir ' . $dir, true);
            @rmdir($base_path . $dir);
        }

        foreach ($data[$lc_name]['moved'] as $moved) {
            $this->_output('restoring backup from ' . $moved . '.backup', true);
            rename($base_path . $moved . '.backup', $base_path . $moved);
        }

        $extension_dir = App::getInstance()->getPath('extensions') . '/' . $name;
        if (is_dir($extension_dir)) {
            $this->_output('removing dir ' . $extension_dir, true);
            Util::removeDir($extension_dir);
        }

        if (isset($data[$lc_name]['routes']) && $data[$lc_name]['routes']) {
            $this->_uninstallRoutes($lc_name);
        }

        // if this is not part of an installation
        if (!$reload) {
            unset($data[$lc_name]);
            $this->_saveInstallationData($data);
            $this->_output('extension: ' . $name . ' uninstalled successfully');
        }
    }

    /**
     * syncs directory 1 with directory 2
     *
     * @var string $dir1 path to directory we are installing from
     * @var string $dir2 path to directory we are installing to
     * @var bool $installed is this extension already installed?
     * @var string $ext_name name of extension
     */
    protected function _sync($dir1, $dir2, $installed = false, $ext_name, $first = false)
    {
        $files = new \RecursiveDirectoryIterator($dir1);

        $file_count = 0;
        foreach ($files as $file) {
            $name = $file->getFilename();

            // ignore any files beginning with . or _
            if (in_array($name[0], array('.', '_'))) {
                continue;
            }

            ++$file_count;

            // do not sync anything in tests directory
            if ($first && $file->isDir() && $file->getFilename() == 'tests') {
                continue;
            }

            // if this is a directory used by the app then copy the files into
            // that directory
            $app_dirs = array('configs', 'controllers', 'libs', 'public_html', 'util', 'views');
            if ($first && $file->isDir() && in_array($file->getFilename(), $app_dirs)) {
                $this->_sync($file->getPathname(), App::getInstance()->getPath($file->getFilename()), $installed, $ext_name);
                continue;
            }

            $old_path = $file->getPath() . '/' . $name;

            $new_name = str_replace($dir1 . DIRECTORY_SEPARATOR, '', $old_path);
            $new_path = $dir2 . '/' . $new_name;

            $this->_createDirectoriesFor($dir2, $new_name, $ext_name);

            if ($file->isDir() && $new_path == App::getInstance()->getPath('libs/Sonic')) {
                $this->_output('WARNING: extensions cannot modify libs/Sonic, skipping dir: ' . $old_path);
                continue;
            }

            if ($file->isDir()) {
                $this->_sync($old_path, $new_path, $installed, $ext_name);
                continue;
            }

            if (!$installed && file_exists($new_path)) {
                $this->_output('WARNING: file already exists at path: ' . $new_path . '! it has been backed up for you');
                copy($new_path, $new_path . '.backup');
                $this->getTracker($ext_name)->moved($this->_stripApp($new_path));
            }

            $this->_output('copying ' . $old_path . ' => ' . $new_path, true);
            copy($old_path, $new_path);
            $perms = fileperms($old_path);
            if (fileperms($new_path) != $perms) {
                $this->_output('updating file permissions to ' . substr(decoct($perms), 2), true);
                chmod($new_path, $perms);
            }
            $this->getTracker($ext_name)->addedFile($this->_stripApp($new_path));
        }

        // if the first directory has no files in it we should create it in the new location
        if ($file_count == 0 && !is_dir($dir2)) {
            $this->_output('creating directory ' . $dir2, true);
            mkdir($dir2);

            // for now assume that an empty directory means the app is going to write to it
            chmod($dir2, 0777);
            $this->getTracker($ext_name)->addedDir($this->_stripApp($dir2));
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
     * gets line added at the top of the routes
     *
     * @param string $name
     * @return string
     */
    protected function _getAddedByLine($name)
    {
        return '# ADDED BY ' . strtoupper($name) . ' EXTENSION - DO NOT EDIT!';
    }

    /**
     * installs routes for this extension
     *
     * @return mixed
     */
    protected function _installRoutes(array $routes, $name)
    {
        $routes = var_export($routes, true);
        $routes_path = $this->_routePath();
        $this->_output('adding routes to ' . $routes_path, true);

        $add = "\n\n";
        $add .= str_repeat('#', 80) . "\n";
        $add .= $this->_getAddedByLine($name) . "\n";
        $add .= str_repeat('#', 80) . "\n";
        $add .= '$' . $name . '_routes = ' . $routes . ';';
        $add .= "\n";
        $add .= '$routes = array_merge($' . $name . '_routes, $routes);' . "\n";
        $add .= str_repeat('#', 80) . "\n";

        return file_put_contents($routes_path, $add, FILE_APPEND);
    }

    /**
     * uninstalls routes for an extension
     *
     * @return void
     */
    protected function _uninstallRoutes($name)
    {
        $routes_path = $this->_routePath();
        $this->_output('removing routes from ' . $routes_path, true);
        $contents = file_get_contents($routes_path);
        $contents = preg_replace('/((\n{2})?(#+\n)' . $this->_getAddedByLine($name) . '(.*?)(;\n#+\n))/s', '', $contents);
        return file_put_contents($routes_path, $contents);
    }

    /**
     * gets path for routes
     *
     * @return string
     */
    protected function _routePath()
    {
        return App::getInstance()->getPath('configs/routes.php');
    }

    /**
     * gets class name of delegate from file
     *
     * @param string $path
     * @return string
     */
    protected function _getDelegateClassName($delegate)
    {
        $path = App::getInstance()->getPath() . DIRECTORY_SEPARATOR . $delegate;
        App::getInstance()->includeFile('Sonic/Extension/Delegate.php');
        $classes_before = get_declared_classes();
        include $path;
        $classes_after = get_declared_classes();
        $diff = array_values(array_diff($classes_after, $classes_before));
        return $diff[0];
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
     * strips the filename off the path and returns the whole path with it lowercase
     *
     * @param string $path
     * @return string
     */
    protected function _lowercaseEnd($path)
    {
        $path_bits = explode(DIRECTORY_SEPARATOR, $path);
        $file = strtolower(array_pop($path_bits));
        if (count($path_bits) == 0) {
            return $file;
        }
        $path = implode(DIRECTORY_SEPARATOR, $path_bits) . DIRECTORY_SEPARATOR . $file;
        return $path;
    }

    /**
     * gets extension name from path
     *
     * @return string
     */
    protected function _nameFromPath($path)
    {
        $path_bits = explode(DIRECTORY_SEPARATOR, $path);
        return array_pop($path_bits);
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
     * gets tmp directory for remote installation
     *
     * @return string
     */
    protected function _getTmpPath()
    {
        return App::getInstance()->getPath('tmp_extensions');
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
     * returns a dummy config ini file from an array
     *
     * @param array $defaults
     * @return string
     */
    protected function _configFromArray($defaults)
    {
        $contents = '[global]' . "\n";

        $translate_value = function($value) {
            if (is_numeric($value) || is_bool($value)) {
                return (int) $value;
            }

            return '"' . $value . '"';
        };

        foreach ($defaults as $key => $value) {
            $contents .= $key . ' = ' . $translate_value($value) . "\n";
        }

        $contents .= "\n[production : global]\n\n[development : global]\n";

        return $contents;
    }

    /**
     * shows usage
     *
     * @return string
     */
    public function showUsage()
    {
        $usage = "./util/extension.php (install|upgrade|uninstall|reload) NameOfExtension\n";
        $usage .= "OR\n";
        $usage .= "./util/extension.php list (installed|outdated)\n\n";
        $usage .= "--help           shows this menu\n";
        $usage .= "--force          forces the extension to install/uninstall\n";
        $usage .= "--verbose        shows verbose output\n";

        return $usage;
    }
}
