#!/usr/bin/env php
<?php
/**
 * syncs the database with your object definitions
 *
 * @author Craig Campbell
 */
use \Sonic\App;
use \Sonic\Database\Sync;
$lib_path = str_replace('/util/sync_db.php', '/libs', realpath(__FILE__));

set_include_path($lib_path);

include 'Sonic/Core.php';
$app = App::getInstance();
$app->addSetting(App::AUTOLOAD, true);

// if we would prefer mysql_query over pdo
if (in_array('--no-pdo', $_SERVER['argv'])) {
    $app->addSetting(App::FAKE_PDO, true);
}

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
