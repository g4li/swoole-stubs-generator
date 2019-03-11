<?php


namespace App;


use ReflectionException;
use ReflectionParameter;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Reflection\ParameterReflection;

/**
 * Class Parameter
 * @package App
 */
class Parameter extends ParameterGenerator
{
    public function __construct($name = null, $type = null, $defaultValue = null, $position = null, $passByReference = false)
    {
        parent::__construct($name, $type, $defaultValue, $position, $passByReference);
    }


    public static function fromPhpReflection(ReflectionParameter $reflection)
    {
        $class = $reflection->getDeclaringClass();
        $callable_name = $reflection->getDeclaringFunction()->getName();

        // @link https://secure.php.net/manual/en/reflectionparameter.construct.php#106781
        $function = is_null($class) ? $callable_name : [$class->getName(), $callable_name];

        return self::fromReflection(new ParameterReflection($function, $reflection->getName()));
    }

    public static function fromReflection(ParameterReflection $reflectionParameter)
    {
        // why not self?
        $param = new self();

        $param->setName($reflectionParameter->getName());

//        if ($type = self::extractFQCNTypeFromReflectionType($reflectionParameter)) {
//            $param->setType($type);
//        }

        $param->setPosition($reflectionParameter->getPosition());

        $variadic = method_exists($reflectionParameter, 'isVariadic') && $reflectionParameter->isVariadic();

        $param->setVariadic($variadic);

        if (! $variadic && ($reflectionParameter->isOptional() || $reflectionParameter->isDefaultValueAvailable())) {
            try {
                $param->setDefaultValue($reflectionParameter->getDefaultValue());
            } catch (ReflectionException $e) {
                $param->setDefaultValue(null);
            }
        }

        $param->setPassedByReference($reflectionParameter->isPassedByReference());

        return $param;
    }
}