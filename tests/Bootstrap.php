<?php
declare(strict_types=1);
namespace MensBeam\Logger\Test;

ini_set('memory_limit', '-1');
ini_set('zend.assertions', '1');
ini_set('assert.exception', 'true');
error_reporting(\E_ALL);
require_once dirname(__DIR__) . '/vendor/autoload.php';

define('CWD', getcwd());