<?php

/**
 * @file plugins/generic/emspubcore/tests/bootstrap.php
 *
 * Bootstrap file for PHPUnit tests
 */

// Define OJS constants if not already defined
if (!defined('RUNNING_UPGRADE')) {
    define('RUNNING_UPGRADE', false);
}

// Load composer autoloader
$vendorPath = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorPath)) {
    require_once $vendorPath;
}

// Load OJS autoloader if available
$ojsAutoloader = dirname(__DIR__, 4) . '/lib/pkp/classes/autoload.php';
if (file_exists($ojsAutoloader)) {
    require_once $ojsAutoloader;
}
