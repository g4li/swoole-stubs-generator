<?php


namespace App\Swoole;


use App\LineAnalyzer;
use App\Parameter;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Zend\Code\Generator\ValueGenerator;

class FunctionMethod implements LineAnalyzer
{

    private static $finished = false;
    private static $inner_assigned_variables = [];

    /** @var ReflectionFunctionAbstract|ReflectionFunction|ReflectionMethod */
    private static $currentCallable = null;

    /** @var Parameter[][] */
    private static $innerParameters = [];

    /**
     * @param string $line
     * @return bool
     */
    public static function matching(string $line): bool
    {
        if (is_null(self::$currentCallable)) {
            if (MethodRegister::matching($line)) {
                self::$currentCallable = MethodRegister::$currentMethod;
                self::initInnerParameterArray();
                return true;
            }
            if (FunctionRegister::matching($line)) {
                self::$currentCallable = FunctionRegister::$currentFunction;
                self::initInnerParameterArray();
                return true;
            }

            return false;
        }

        if (rtrim($line) == "}" || self::$finished) {
            self::$finished = false;
            self::$inner_assigned_variables = [];
            self::$currentCallable = null;

            return true;
        }

        if (MultipleParameter::matching($line)) {
            if (!MultipleParameter::isOpening()) {
                self::$finished = true;
            }
            return true;
        }

        if (OneLineParameter::matching($line)) {
            self::$finished = true;
            return true;
        }

        self::innerAssign($line);

        return true;
    }

    /**
     * @return ReflectionFunction|ReflectionMethod
     */
    public static function getCurrentCallable()
    {
        return self::$currentCallable;
    }

    private static function innerAssign($line)
    {
        // long sock_type = SW_SOCK_TCP;
        if (preg_match('/[a-z0-9\_]+[\s]+(.*?(?=\=))\=(.*?(?=[;,]{1}))/', $line, $matches)) {
            $inner_variable_name = trim($matches[1], '*& ');
            $value = trim($matches[2]);
            if ($value == 'NULL') {
                $value = null;
            }
            self::$inner_assigned_variables[$inner_variable_name] = $value;
        }
    }

    public static function hasAssignedValue(string $name): bool
    {
        return array_key_exists($name, self::$inner_assigned_variables);
    }

    public static function getAssignedValue(string $name)
    {
        return self::$inner_assigned_variables[$name];
    }

    public static function initInnerParameterArray()
    {
        $callable = self::$currentCallable;

        if ($callable instanceof ReflectionMethod) {
            $temp = $callable->getDeclaringClass()->getName() . '::' . $callable->getName();
        } else {
            $temp = $callable->getName();
        }
        self::$innerParameters[$temp] = [];
    }

    /**
     * @param Parameter $parameter
     * @param string $inner_name
     * @param bool $optional
     */
    public static function setInnerParameters(Parameter $parameter, string $inner_name, $optional = false)
    {
        $callable = self::$currentCallable;

        if ($callable instanceof ReflectionMethod) {
            $temp = $callable->getDeclaringClass()->getName() . '::' . $callable->getName();
        } else {
            $temp = $callable->getName();
        }
        self::$innerParameters[$temp][] = $parameter;

        if ($optional && self::hasAssignedValue($inner_name)) {
            $value = self::getAssignedValue($inner_name);
            $constant_name = ConstantRegister::getNamedConstant($value);
            if ($constant_name) {
                $type = ValueGenerator::TYPE_CONSTANT;
                $value = $constant_name;
            } elseif (!empty($parameter->getType())) {
                $type = (string)$parameter->getType();
                settype($value, $type);
            } else {
                $type = ValueGenerator::TYPE_AUTO;
            }
            $defaultValue = new ValueGenerator($value, $type);
            $parameter->setDefaultValue($defaultValue);
        }
    }

    /**
     * @param ReflectionFunctionAbstract $callable
     * @param int $index
     * @return Parameter
     */
    public static function getInnerParameter(ReflectionFunctionAbstract $callable, int $index = -1)
    {
        if ($callable instanceof ReflectionMethod) {
            $temp = $callable->getDeclaringClass()->getName() . '::' . $callable->getName();
        } else {
            $temp = $callable->getName();
        }

        $parameters = self::$innerParameters[$temp];

        if ($index == -1) {
            $index = count($parameters) - 1;
        }

        return $parameters[$index];
    }

    public static function getInnerParameters(string $method_string)
    {
        return self::$innerParameters[$method_string] ?? [];
    }
}