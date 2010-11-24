#!/usr/bin/env php
<?php
/**
 * crazy script used to install or remove sonic extensions
 *
 * @author Craig Campbell
 */
use \Sonic\App;
$lib_path = str_replace('/util/extension.php', '/libs', realpath(__FILE__));

set_include_path($lib_path);

function usageAndExit()
{
    echo "./util/extension.php (install|upgrade|uninstall|reload) name-of-extension","\n\n";
    echo "-h,--help         shows this menu","\n";
    exit;
}

function getDirectoriesFor($file)
{
    $directories = array();
    $file = str_replace('libs/Sonic/', '', $file);
    $bits = explode('/', $file);
    $name = array_pop($bits);

    $last_dir = 'libs/Sonic';
    foreach ($bits as $bit) {
        $last_dir = $last_dir . '/' . $bit;
        $directories[] = $last_dir;
    }
    return $directories;
}

if (count($_SERVER['argv']) < 3 || in_array('-h', $_SERVER['argv']) || in_array('--help', $_SERVER['argv'])) {
    usageAndExit();
}

include 'Sonic/App.php';
$app = App::getInstance();
$app->addSetting(App::AUTOLOAD, true);
$app->start(App::COMMAND_LINE);

$type = $_SERVER['argv'][1];
$extension_name = strtolower($_SERVER['argv'][2]);

$extension_path = $app->getPath('extensions') . '/' . $extension_name;

// make sure the extension exists
if ($type != 'uninstall' && !is_dir($extension_path)) {
    echo 'extension with name: ' . $extension_name . ' not found at path: ' . $app->getPath('extensions') . "\n";
    exit;
}

// make sure the manifest file was found
if ($type != 'uninstall' && !file_exists($extension_path . '/_manifest.php')) {
    echo "no manifest file found at " . $extension_path . "\n";
    exit;
}

// get the extension configuration
if ($type != 'uninstall') {
    include $extension_path . '/_manifest.php';
    $map = $config['map'];
}

$installed = $app->getPath('extensions') . '/installed.json';
if (!file_exists($installed)) {
    $installation_data = array();
    file_put_contents($installed, json_encode($installation_data));
}

if (!isset($installation_data)) {
    $installation_data = json_decode(file_get_contents($installed), true);
}

$added = array();
if (isset($installation_data[$extension_name]['added'])) {
    $added = $installation_data[$extension_name]['added'];
}

$mkdir = array();
if (isset($installation_data[$extension_name]['mkdir'])) {
    $mkdir = $installation_data[$extension_name]['mkdir'];
}

switch ($type) {
    case 'upgrade':
    case 'install':
        if (isset($installation_data[$extension_name]['version'])) {
            $version = $installation_data[$extension_name]['version'];
            if ($config['version'] == $version) {
                echo 'extension is already installed at version: ',$version,"\n";
                break;
            }
            if ($config['version'] <= $version) {
                echo 'extension version: ' . $config['version'] . ' is older than installed version: ' . $version . "\n";
                break;
            }
        }
    case 'reload':
        $installation_data[$extension_name] = array();
        $installation_data[$extension_name]['version'] = $config['version'];
        $installation_data[$extension_name]['moved'] = array();
        $installation_data[$extension_name]['added'] = array();
        $installation_data[$extension_name]['mkdir'] = array();
        $files = array_keys($map);
        foreach ($files as $file) {
            if (!file_exists($extension_path . '/'. $file)) {
                echo 'file: ' . $file . ' not found in: ' . $extension_path . "\n";
                exit;
            }
        }

        foreach ($map as $file => $info) {
            $old_file = $info['file'];
            $bits = explode('/', $old_file);
            $name = array_pop($bits);
            $bits[] = '_' . $name;
            $new_file = implode('/', $bits);

            $add = !file_exists($app->getPath($old_file)) || in_array($old_file, $added);

            if ($add) {
                $installation_data[$extension_name]['added'][] = $old_file;
                $directories = getDirectoriesFor($old_file);
                foreach ($directories as $directory) {
                    if (!is_dir($app->getPath($directory)) || in_array($directory, $mkdir)) {
                        $installation_data[$extension_name]['mkdir'][] = $directory;
                    }

                    if (!is_dir($app->getPath($directory))) {
                        mkdir($app->getPath($directory));
                    }
                }

            } else {
                $installation_data[$extension_name]['moved'][$old_file] = $new_file;
                rename($app->getPath($old_file), $app->getPath($new_file));
            }

            copy($extension_path . '/' . $file, $app->getPath($old_file));

            if (isset($info['extends']) && $info['extends']) {
                $contents = file_get_contents($app->getPath($old_file));
                $class_name = str_replace('.php', '', $name);
                $contents = str_replace('extends ' . $class_name, 'extends _' . $class_name, $contents);
                file_put_contents($app->getPath($old_file), $contents);

                $contents = file_get_contents($app->getPath($new_file));
                $contents = str_replace('class ' . $class_name, 'class _' . $class_name, $contents);
                file_put_contents($app->getPath($new_file), $contents);
            }
        }
        echo "installation successful\n";
        break;
    case 'uninstall':
        if (!isset($installation_data[$extension_name])) {
            echo 'extension: ' . $extension_name . ' is not currently installed',"\n";
            exit;
        }

        $data = $installation_data[$extension_name];
        foreach ($data['added'] as $file) {
            unlink($app->getPath($file));
        }

        foreach ($data['moved'] as $file => $original_file) {
            unlink($app->getPath($file));
            rename($app->getPath($original_file), $app->getPath($file));
            $bits = explode('/', $file);
            $name = array_pop($bits);
            $class_name = str_replace('.php', '', $name);

            $contents = file_get_contents($app->getPath($file));
            $contents = str_replace('class _' . $class_name, 'class ' . $class_name, $contents);
            file_put_contents($app->getPath($file), $contents);
        }

        $dirs_to_delete = array_reverse($data['mkdir']);
        foreach ($dirs_to_delete as $dir) {
            rmdir($app->getPath($dir));
        }

        unset($installation_data[$extension_name]);
        echo "uninstall successful\n";
        break;
}

file_put_contents($installed, json_encode($installation_data));
