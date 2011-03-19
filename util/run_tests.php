#!/usr/bin/env php
<?php
/**
 * Runs unit tests
 *
 * @author Craig Campbell
 */
use Sonic\UnitTest\Runner;
$lib_path = str_replace(DIRECTORY_SEPARATOR . 'util', '', __DIR__) . DIRECTORY_SEPARATOR . 'lib';
include $lib_path . '/Sonic/UnitTest/Runner.php';

try {
    Runner::start($_SERVER['argv']);
} catch (\Exception $e) {
    echo $e->getMessage(),"\n";
}
