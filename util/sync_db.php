#!/usr/bin/php
<?php
/**
 * syncs the database with your object definitions
 *
 * @author Craig Campbell
 */
$lib_path = str_replace('/util/sync_db.php', '/lib', realpath(__FILE__));
set_include_path($lib_path);

use \Sonic\App;

include 'Sonic/App.php';
$app = App::getInstance();
$app->addSetting('config_file', 'ini');
$app->start(App::COMMAND_LINE);

\Sonic\Object\Sync::run();
