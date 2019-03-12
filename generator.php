<?php
require 'vendor/autoload.php';

define('OUTPUT_DIR', __DIR__ . '/../swoole-stubs');
define('SWOOLE_SRC', __DIR__ . '/../swoole-src');

App\Generator::exec();
