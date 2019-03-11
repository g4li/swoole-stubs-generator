<?php


namespace App\Swoole;


use App\LineAnalyzer;
use Exception;
use ReflectionClass;

class ClassRegister implements LineAnalyzer
{

    /** @var ReflectionClass[] */
    private static $classes = [];

    /** @var ReflectionClass[] */
    private static $module_classes = [];

    /** @var string[] */
    private static $class_alias = [];

    /** @var string[] */
    private static $notFound = [];

    /**
     * @param string $line
     * @return bool
     * @throws \ReflectionException
     * @throws Exception
     */
    public static function matching(string $line): bool
    {
        if (strpos($line, 'SWOOLE_INIT_CLASS_ENTRY') === false) {
            return false;
        }

        // swoole 4.2
        if (preg_match('/SWOOLE_INIT_CLASS_ENTRY[A-Z\_]*\(([a-zA-Z0-9\_]+), "([a-zA-Z0-9\\\\]+)", (["a-zA-Z0-9\_\\\\]+), (["a-zA-Z0-9\_\\\\]+),/', $line, $matches)) {
            $module = $matches[1];
            $temp = explode('\\\\', trim($matches[2], '" '));
            $class_name = array_pop($temp);
            $alias = array_slice($matches, 3, 2);
        } else {
            throw new Exception('class pattern error, code=' . $line);
        }

        if ($class_name == 'NULL') {
            return true;
        }

        $namespace = implode('\\', $temp);
        $namespace_name = $namespace . '\\' . $class_name;

        // @todo class not fund in installed swoole extension
        if (!class_exists($namespace_name)) {
            self::$notFound[] = $namespace_name;
            return true;
        }
        $class = new ReflectionClass($namespace_name);

        self::$classes[$namespace_name] = $class;
        self::$module_classes[$module] = $class;

        foreach ($alias as $value) {
            if ($value !== 'NULL') {
                $alias = str_replace('\\\\', '\\', trim($value, '"'));
                self::$class_alias[$alias] = $namespace_name;
            }
        }
        return true;
    }

    public static function hasClassByModule(string $module): bool
    {
        return array_key_exists($module, self::$module_classes);
    }

    public static function getClassByModule(string $module): ReflectionClass
    {
        return self::$module_classes[$module];
    }

    public static function hasClassByName(string $name): bool
    {
        return array_key_exists($name, self::$classes);
    }

    public static function getClassByName(string $name): ReflectionClass
    {
        return self::$classes[$name];
    }
}