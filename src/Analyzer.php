<?php

namespace App;

use App\Swoole\FunctionMethod;
use App\Swoole\ClassRegister;
use App\Swoole\ConstantRegister;


/**
 * Class Analyzer
 * @package App
 */
class Analyzer
{
    public function __construct($directory)
    {
        $files = array_unique(array_merge(
            [$directory . '/swoole.c'],
            glob($directory . '/*.c'),
            glob($directory . '/*.cc')
        ));
        foreach ($files as $file) {
            $this->lineByLine($file);
        }
    }

    protected function lineByLine($file)
    {
        foreach (file($file) as $ln => $line) {
            if (ClassRegister::matching($line)) {
                continue;
            }

            if (FunctionMethod::matching($line)) {
                continue;
            }

            if (ConstantRegister::matching($line)) {
                continue;
            }
        }
    }

}