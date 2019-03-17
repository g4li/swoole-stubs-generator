<?php


namespace App\Swoole;


use App\LineAnalyzer;

class MethodAliasRegister implements LineAnalyzer
{
    /** @var null 正在处理的函数列表 */
    private static $currentMethods = null;

    /** @var string[][] 方法->函数 别名  */
    private static $methodToFunctions = [];

    /** @var string[][] 方法->方法 别名  */
    private static $methodToMethods = [];

    public static function matching(string $line): bool
    {
        if (is_null(self::$currentMethods)) {
            // static const zend_function_entry swoole_timer_methods[] =
            // static zend_function_entry swoole_server_methods[] =
            if (preg_match('/static[\s]+(const[\s]+|)zend_function_entry[\s]+([a-zA-Z0-9\_]+)/', $line, $matches)){
                self::$currentMethods = $matches[2];
                self::$methodToFunctions[self::$currentMethods] = array();
                return true;
            }

            return false;
        }

        // PHP_FE_END
        if (strpos($line, 'PHP_FE_END') !== false) {
            self::$currentMethods = null;
            return true;
        }

        // ZEND_FENTRY(tick, ZEND_FN(swoole_timer_tick), arginfo_swoole_timer_tick, ZEND_ACC_PUBLIC | ZEND_ACC_STATIC)
        if (preg_match('/ZEND_FENTRY\((.*?(?=,)),[\s]*ZEND_FN\((.*?(?=\)))/', $line, $matches)) {
            $function_name = $matches[2];
            $method_alias = $matches[1];

            self::$methodToFunctions[self::$currentMethods][$method_alias] = $function_name;
            return true;
        }

        // PHP_FALIAS(after, swoole_timer_after, arginfo_swoole_timer_after)
        if (preg_match('/PHP_FALIAS\((.*?(?=,)),[\s]*(.*?(?=,)),/', $line, $matches)) {
            $function_name = $matches[2];
            $method_alias = $matches[1];

            self::$methodToFunctions[self::$currentMethods][$method_alias] = $function_name;
            return true;
        }

        // PHP_MALIAS(swoole_server, addlistener, listen, arginfo_swoole_server_listen, ZEND_ACC_PUBLIC)
        if (preg_match('/PHP_MALIAS\((.*?(?=,)),[\s]*(.*?(?=,)),[\s]*(.*?(?=,)),/', $line, $matches)) {
            $function_name = $matches[3];
            $method_alias = $matches[2];

            self::$methodToMethods[self::$currentMethods][$method_alias] = $function_name;
            return true;
        }

        return true;
    }

    public static function isFunctionAlias(string $module_name, string $method_name)
    {
        return isset(self::$methodToFunctions[$module_name][$method_name]);
    }

    public static function getFunctionAlias(string $module_name, string $method_name)
    {
        return self::$methodToFunctions[$module_name][$method_name];
    }

    public static function isMethodAlias(string $module_name, string $method_name)
    {
        return isset(self::$methodToMethods[$module_name][$method_name]);
    }

    public static function getMethodAlias(string $module_name, string $method_name)
    {
        return self::$methodToMethods[$module_name][$method_name];
    }
}