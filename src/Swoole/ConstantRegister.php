<?php


namespace App\Swoole;


use App\LineAnalyzer;

class ConstantRegister implements LineAnalyzer
{

    public static function matching(string $line): bool
    {
        return false;
    }

    public static function getNamedConstant($name): string
    {
        if (defined($name)) {
            return $name;
        }

        $constant = preg_replace('/^SW\_/', 'SWOOLE_', $name, 1, $count);
        if($count && defined($constant)) {
            return $constant;
        }

        $constant = preg_replace('/^SW\_/', '', $name, 1, $count);
        if($count && defined($constant)) {
            return $constant;
        }

        $constant = preg_replace('/^SW_MODE\_/', 'SWOOLE_', $name, 1, $count);
        if($count && defined($constant)) {
            return $constant;
        }

        return '';
    }
}