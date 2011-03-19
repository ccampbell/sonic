<?php
use \Sonic\App, \Sonic\Util;

$app = App::getInstance();

// remove the symlink to sonic
Util::removeDir($app->getPath(DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'Sonic'));
