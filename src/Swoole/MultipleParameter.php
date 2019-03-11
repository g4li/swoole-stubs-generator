<?php


namespace App\Swoole;


use App\LineAnalyzer;
use App\Parameter;
use RuntimeException;

class MultipleParameter implements LineAnalyzer
{

    private static $opening = false;

    private static $index = PHP_INT_MIN;

    public static function matching(string $line): bool
    {
        if (!self::$opening) {
            // ZEND_PARSE_PARAMETERS_START(2, 3)
            if (strpos($line, 'ZEND_PARSE_PARAMETERS_START') !== false) {
                /**
                 * @link https://phpinternals.net/categories/zend_parameter_parsing
                 */
                self::$opening = true;
                self::$index = 0;
                return true;
            }
            return false;
        }

        // ZEND_PARSE_PARAMETERS_END();
        if (strpos($line, 'ZEND_PARSE_PARAMETERS_END') !== false) {
            // no more callable analyzing after get all parameters
            self::$opening = false;
            self::$index = PHP_INT_MIN;
            return true;
        }

        // Z_PARAM_LONG(port)
        // Z_PARAM_OPTIONAL
        if (!preg_match('/Z_PARAM_([A-Z\_]+)\((.*?(?=\)))\)/', $line, $matches)) {
            return true;
        }

        $type_map = [
            'BOOL' => 'bool',
            'LONG' => 'int',
            'DOUBLE' => 'float',
            'STRING' => 'string',
            'STR' => 'string',
            'ARRAY' => 'array',
            'FUNC' => 'callable',
            'ZVAL' => null,
            'RESOURCE' => null,
        ];
        $type = $matches[1];
        if (($temp = strpos($type, '_')) !== false) {
            $type = substr($type, 0, $temp);
        }

        $callable = FunctionMethod::getCurrentCallable();

        if ($type == 'VARIADIC') {
            if (self::$index == $callable->getNumberOfParameters() - 1) {
                $parameter = FunctionMethod::getInnerParameter($callable);
            } else {
                $parameter = new Parameter(
                    'args',
                    null,
                    null,
                    self::$index++
                );
                FunctionMethod::setInnerParameters($parameter, 'args', true);
            }
            $parameter->setVariadic(true);
            return true;
        }

        if (!array_key_exists($type, $type_map)) {
            throw new RuntimeException('parameter type error, type=' . $type);
        }

        list($inner_name,) = explode(',', $matches[2], 2);

        $optional = self::$index >= $callable->getNumberOfRequiredParameters();
        if (self::$index < $callable->getNumberOfParameters()) {
            $reflection = $callable->getParameters()[self::$index++];
            $parameter = Parameter::fromPhpReflection($reflection);
        } else {
            // not proclaimed parameter
            $parameter = new Parameter($inner_name, null, null, self::$index++);
        }

        if (!$parameter->getType() && $type_map[$type]) {
            $parameter->setType($type_map[$type]);
        }

        FunctionMethod::setInnerParameters($parameter, $inner_name, $optional);
        return true;
    }

    /**
     * @return bool
     */
    public static function isOpening(): bool
    {
        return self::$opening;
    }
}