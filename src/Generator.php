<?php


namespace App;

use App\Swoole\ClassRegister;
use App\Swoole\MethodAliasRegister;
use App\Swoole\FunctionMethod;
use App\Swoole\FunctionRegister;
use App\Swoole\MethodRegister;
use ReflectionClass;
use ReflectionExtension;
use ReflectionFunction;
use Traversable;
use function var_export;
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

    /** @var Analyzer */
    public static $analyzer = null;

    public static function exec()
    {
        defined("OUTPUT_DIR") || define("OUTPUT_DIR", __DIR__ . '/../stubs');

        defined("SWOOLE_SRC") || define("SWOOLE_SRC", __DIR__ . '/../swoole-src');

        self::$analyzer = new Analyzer(SWOOLE_SRC);
        self::constants();
        self::classes();
        self::classAliases();
        self::functions();

        self::notFound();
    }

    public static function constants()
    {
        $ref = new ReflectionExtension('Swoole');
        $output = '';
        foreach ($ref->getConstants() as $constant => $value) {
            $output .= sprintf('define("%s", %s);' . PHP_EOL, $constant, var_export($value, true));
        }

        self::writeFile($output, OUTPUT_DIR . '/constants.php');
    }

    public static function classes()
    {
        $ref = new ReflectionExtension('Swoole');

        foreach ($ref->getClasses() as $class) {
            $reflection = new ClassReflection($class->getName());
            $classGenerator = ClassGenerator::fromReflection($reflection);

            $docBlock = new DocBlockGenerator();
            $classGenerator->setDocBlock($docBlock);

            if ($classGenerator->hasImplementedInterface(Traversable::class)) {
                // 去掉重复的 interface
                $classGenerator->removeImplementedInterface(Traversable::class);
            }

            // private 是不需要显示的, public 都是只读的，protected 是稀少而有可能被读写的
            foreach ($classGenerator->getProperties() as $property_name => $property) {
                $classGenerator->removeProperty($property->getName());

                if ($property->getVisibility() !== PropertyGenerator::VISIBILITY_PRIVATE) {
                    $docBlock->setTag(new GenericTag('property-read', '$' . $property->getName()));
                }
            }

            $linkTag = self::linkTag(str_replace('Swoole\\', '', $class->getName()));
            if (!empty($linkTag)) {
                $docBlock->setTag($linkTag);
            }

            foreach ($classGenerator->getMethods() as $method) {
                if ($method->getName() == '__destruct') {
                    $classGenerator->removeMethod($method->getName());
                }
                self::callable($method, $class);

                $class_method = ClassRegister::getClassMethod($class->getName());
                if (MethodAliasRegister::isFunctionAlias($class_method, $method->getName())) {
                    // 该方法为某函数的别名，通过代码声明，不去获取该方法的参数类型
                    $function_name = MethodAliasRegister::getFunctionAlias($class_method, $method->getName());
                    $method->setBody(sprintf('return call_user_func_array("%s", func_get_args());', $function_name));
                }

                if (MethodAliasRegister::isMethodAlias($class_method, $method->getName())) {
                    // 该方法为某函数的别名，通过代码声明，不去获取该方法的参数类型
                    $function_name = MethodAliasRegister::getMethodAlias($class_method, $method->getName());
                    $method->setBody(sprintf('return call_user_func_array([$this, "%s"], func_get_args());', $function_name));
                }

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

    public static function classAliases()
    {
        $output = '';
        foreach (ClassRegister::getClassAliases() as $alias => $namespace_name) {
            if (class_exists($alias)) {
                $output .= sprintf('class_alias(%s::class, "%s");' . PHP_EOL, $namespace_name, $alias);
            }
        }

        $file = new FileGenerator();
        $file->setBody($output);
        $file->setFilename(OUTPUT_DIR . '/aliases.php');
        $file->write();
    }

    public static function functions()
    {
        $ref = new ReflectionExtension('Swoole');
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
        if ($reflection instanceof ReflectionFunction) {
            $linkTag = self::linkTag($callableGenerator->getName());

            // 初始化 函数 的参数
            if ($reflection->getNumberOfParameters() > 0) {
                $zendReflection = new FunctionReflection($reflection->getName());

                foreach ($zendReflection->getParameters() as $parameter) {
                    $parameterGenerator = ParameterGenerator::fromReflection($parameter);
                    $callableGenerator->setParameter($parameterGenerator);
                }
            }
            $analyzer_parameter_index = $callableGenerator->getName();
        } else {
            /** @var ReflectionClass $reflection */
            $connector = $callableGenerator->isStatic() ? '::' : '->';
            $wiki_names = [
                str_replace('Swoole\\', '', $reflection->getName()) . $connector . $callableGenerator->getName(),
                str_replace('\\', '_', $reflection->getName()) . $connector . $callableGenerator->getName(),
            ];
            do {
                $linkTag = self::linkTag(array_shift($wiki_names));
            } while(is_null($linkTag) && !empty($wiki_names));
            $analyzer_parameter_index = $reflection->getName() . '::' . $callableGenerator->getName();
        }

        $tags = [];
        if ($linkTag) {
            $tags[] = $linkTag;
        }

        $analyzerParameters = FunctionMethod::getInnerParameters($analyzer_parameter_index);
        $parameters = $callableGenerator->getParameters();

        /** @var ParameterGenerator $analyzerParameter */
        foreach ($analyzerParameters as $analyzerParameter) {
            $parameter_name = $analyzerParameter->getName();

            if (array_key_exists($parameter_name, $parameters)) {
                $parameter = $parameters[$parameter_name];
                if ($analyzerParameter->getType()) {
                    $parameter->setType($analyzerParameter->getType());
                }
                if ($analyzerParameter->getDefaultValue()) {
                    $parameter->setDefaultValue($analyzerParameter->getDefaultValue());
                }
            } else {
                $callableGenerator->setParameter($analyzerParameter);
            }
        }

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

    private static function notFound()
    {

        $output = '';

        foreach (
            [
                'classes' => ClassRegister::class,
                'methods' => MethodRegister::class,
                'functions' => FunctionRegister::class,
            ] as $type => $class_name) {
            $values = call_user_func([$class_name, 'getNotFound']);

            if (count($values) == 0) {
                continue;
            }

            $output .= sprintf('%d %s not found in installed extension' . PHP_EOL, count($values), $type);
            foreach ($values as $value) {
                $output .= "\t" . $value . PHP_EOL;
            }
            $output .= PHP_EOL;
        }

        $log_file = OUTPUT_DIR . '/not-found.txt';
        $fp = fopen($log_file, 'w+');
        ftruncate($fp, 0);

        fwrite($fp, $output);

        fclose($fp);
    }
}

