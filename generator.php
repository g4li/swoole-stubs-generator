<?php
require 'vendor/autoload.php';

define('OUTPUT_DIR', __DIR__ . '/../swoole-stubs');

App\Generator::exec();
