#!/usr/bin/env php
<?php
/**
 * Runs unit tests
 *
 * @author Craig Campbell
 */
use Sonic\UnitTest\Runner;
$lib_path = str_replace('run_tests.php', '', realpath(__FILE__)) . '/../lib';
include $lib_path . '/Sonic/UnitTest/Runner.php';

Runner::start($_SERVER['argv']);
