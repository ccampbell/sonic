<?php
use \Sonic\App;

ini_set('memory_limit', '1024M');

$base_path = str_replace(DIRECTORY_SEPARATOR . 'tests', '', __DIR__);
$app_path = $base_path . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'app';

// symlink the sonic library to the tests/app/libs directory
// as far as the tests are concerned they are all running out of tests/app
symlink($base_path . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Sonic', $app_path . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'Sonic');
include $app_path . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'Sonic' . DIRECTORY_SEPARATOR . 'App.php';

$app = App::getInstance();
$app->setBasePath($app_path);

$app->addSetting(App::AUTOLOAD, true);

$app->start(App::COMMAND_LINE);
