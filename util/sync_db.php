#!/usr/bin/php
<?php
/**
 * syncs the database with your object definitions
 *
 * @author Craig Campbell
 */
use \Sonic\App;
use \Sonic\Database\Sync;
$lib_path = str_replace('/util/sync_db.php', '/lib', realpath(__FILE__));

set_include_path($lib_path);

include 'Sonic/App.php';
$app = App::getInstance();
$app->addSetting('config_file', 'ini');
$app->start(App::COMMAND_LINE);

// dry run - outputs sql but doesn't run it
if (in_array('--dry-run', $_SERVER['argv'])) {
    Sync::dryRun();
}

// verbose mode
if (in_array('-v', $_SERVER['argv']) || in_array('--verbose', $_SERVER['argv'])) {
    Sync::verbose();
}

Sync::run();
