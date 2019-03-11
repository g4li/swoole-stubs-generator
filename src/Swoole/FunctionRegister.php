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
        if (!preg_match('/^static PHP_FUNCTION\((.*)\)$/', $line, $matches)) {
            return false;
        }

        $function_name = $matches[1];
        try {
            $currentFunction = new ReflectionFunction($function_name);
        } catch (Throwable $exception) {
            // @todo function is not fund in installed extension
            self::$notFound[] = $function_name;
            return true;
        }

        self::$currentFunction = $currentFunction;
        return true;
    }
}