<?php


namespace App;

use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlock\Tag\GenericTag;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\ParameterGenerator;
use Zend\Code\Generator\PropertyGenerator;
use Zend\Code\Reflection\ClassReflection;
use Zend\Code\Reflection\FunctionReflection;


class Generator
{

    public static function exec()
    {

        defined("OUTPUT_DIR") || define("OUTPUT_DIR", __DIR__ . '/../stubs');

        self::constants();
        self::classes();
        self::functions();
    }

    public static function constants()
    {
        $ref = new \ReflectionExtension('Swoole');
        $output = '';
        foreach ($ref->getConstants() as $constant => $value) {
            $output .= sprintf('define("%s", %s);' . PHP_EOL, $constant, \var_export($value, true));
        }

        self::writeFile($output, OUTPUT_DIR . '/constants.php');
    }

    public static function classes()
    {
        $ref = new \ReflectionExtension('Swoole');

        foreach ($ref->getClasses() as $class) {
            $reflection = new ClassReflection($class->getName());
            $classGenerator = ClassGenerator::fromReflection($reflection);

            $docBlock = new DocBlockGenerator();
            $classGenerator->setDocBlock($docBlock);

            if ($classGenerator->hasImplementedInterface(\Traversable::class)) {
                // 去掉重复的 interface
                $classGenerator->removeImplementedInterface(\Traversable::class);
            }

            // private 是不需要显示的, public 都是只读的，protected 是稀少而有可能被读写的
            foreach ($classGenerator->getProperties() as $property_name => $property) {
                $classGenerator->removeProperty($property->getName());

                if ($property->getVisibility() !== PropertyGenerator::VISIBILITY_PRIVATE) {
                    $docBlock->setTag(new GenericTag('property-read', '$' . $property->getName()));
                }
            }

            $linkTag = self::linkTag($class->getShortName());
            if(!empty($linkTag)) {
                $docBlock->setTag($linkTag);
            }

            foreach ($classGenerator->getMethods() as $method) {
                if ($method->getName() == '__destruct') {
                    $classGenerator->removeMethod($method->getName());
                }
                self::callable($method, $class);
            }

            $fileGenerator = new FileGenerator();
            $fileGenerator->setClass($classGenerator);
            $filename = OUTPUT_DIR . '/' . strtr($class->getName(), ['\\' => '/']) . '.php';
            $fileGenerator->setFilename($filename);

            if (!file_exists(dirname($filename))) {
                mkdir(dirname($filename), 0777, true);
            }

            $fileGenerator->write();
        }
    }

    public static function functions()
    {
        $ref = new \ReflectionExtension('Swoole');
        $output = '';
        foreach ($ref->getFunctions() as $function) {
            $functionGenerator = new MethodGenerator($function->getName());
            $functionGenerator->setIndentation('');

            self::callable($functionGenerator, $function);

            $code = $functionGenerator->generate();
            $code = str_replace(MethodGenerator::VISIBILITY_PUBLIC . ' ', '', $code);
            $output .= $code . PHP_EOL;
        }

        self::writeFile($output, OUTPUT_DIR . '/functions.php');
    }


    private static function writeFile($code, $filename)
    {
        $folder = dirname($filename);
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }

        $fileGenerator = new FileGenerator();
        $fileGenerator->setFilename($filename);
        $fileGenerator->setBody($code);

        return $fileGenerator->write();
    }

    private static function callable(MethodGenerator $callableGenerator, $reflection = null)
    {
        if ($reflection instanceof \ReflectionFunction) {
            $linkTag = self::linkTag($callableGenerator->getName());

            // 初始化 函数 的参数
            if ($reflection->getNumberOfParameters() > 0) {
                $zendReflection = new FunctionReflection($reflection->getName());

                foreach ($zendReflection->getParameters() as $parameter) {
                    $parameterGenerator = ParameterGenerator::fromReflection($parameter);
                    $callableGenerator->setParameter($parameterGenerator);
                }
            }
        } else {
            /** @var \ReflectionClass $reflection */
            $linkTag = self::linkTag(str_replace('Swoole\\', '', $reflection->getName()) . '->' . $callableGenerator->getName())
                ?? self::linkTag(
                    str_replace('\\', '_', $reflection->getName()) . '->' . $callableGenerator->getName()
                );
        }

        $tags = [];
        if ($linkTag) {
            $tags[] = $linkTag;
        }

        // @todo 参数的默认值和数据类型

        if (!empty($tags)) {
            $docBlock = new DocBlockGenerator();
            $docBlock->setWordWrap(false);
            $docBlock->setTags($tags);
            $callableGenerator->setDocBlock($docBlock);
        }
    }

    private static function linkTag($name)
    {
        static $cache;
        if (is_null($cache)) {
            $cache_file = OUTPUT_DIR . '/swoole-wiki-readme.md';
            if (!file_exists($cache_file)) {
                copy('https://raw.githubusercontent.com/swoole/swoole-wiki/master/README.md', $cache_file);
            }
            $cache = file_get_contents($cache_file);
        }

        if (!preg_match('#<a href="doc/(.*?(?="))">' . addcslashes($name, '\\\_->') . '</a>#i', $cache, $matches)) {
            return null;
        }

        $link = 'https://github.com/swoole/swoole-wiki/blob/master/doc/' . rawurlencode($matches[1]);
        return new GenericTag('link', $link);
    }
}

