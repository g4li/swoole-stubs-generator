<?php


namespace App\Swoole;


use App\LineAnalyzer;
use ReflectionFunction;
use Throwable;

class FunctionRegister implements LineAnalyzer
{

    /** @var ReflectionFunction */
    public static $currentFunction = null;

    /** @var string[] */
    private static $notFound = [];

    public static function matching(string $line): bool
    {
        // PHP_FUNCTION(swoole_timer_tick)
        if (!preg_match('/PHP_FUNCTION\((.*)\)$/', $line, $matches)) {
            return false;
        }

        $function_name = $matches[1];
        if (strncmp($function_name, '_', 1) === 0) {
            // _ 起始的函数使用了 PHP_METHOD 语法却并未在扩展中
            return true;
        }

        try {
            $currentFunction = new ReflectionFunction($function_name);
        } catch (Throwable $exception) {
            // @todo function is not fund in installed extension
            self::$notFound[] = $function_name;
            // @todo return true will cause an exception
            return true;
        }

        self::$currentFunction = $currentFunction;
        return true;
    }

    /**
     * @return string[]
     */
    public static function getNotFound(): array
    {
        return self::$notFound;
    }
}