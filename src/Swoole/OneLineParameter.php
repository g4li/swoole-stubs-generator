<?php


namespace App\Swoole;


use App\LineAnalyzer;
use App\Parameter;
use RuntimeException;

class OneLineParameter implements LineAnalyzer
{

    public static function matching(string $line): bool
    {
        // if (zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "sls|l", &ip, &ip_len, &port, &data, &len, &server_socket) == FAILURE)
        if (!preg_match('/zend_parse_parameters\(ZEND_NUM_ARGS\(\), "(.*?(?="))", (.*?(?=\)))\)/', $line, $matches)) {
            return false;
        }

        /**
         * @link https://devzone.zend.com/317/extension-writing-part-ii-parameters-arrays-and-zvals/#Heading2
         * @link https://phpinternals.net/categories/zend_parameter_parsing
         */
        $type_map = [
            'b' => 'bool',
            'l' => 'int',
            'd' => 'float',
            's' => 'string',
            'a' => 'array',
            'z' => null,
        ];

        $type_chars = str_split($matches[1]);
        $parameter_names = explode(',', $matches[2]);
        $index = 0;
        foreach ($type_chars as $i => $char) {
            if ($char == '|') {
                // following parameters are optional
                continue;
            }
            if ($char == '/') {
                // previous parameter is passed by reference
                continue;
            }
            if ($char == '!') {
                // previous parameter is nullable
                continue;
            }

            if (!array_key_exists($char, $type_map)) {
                throw new RuntimeException('parameter type not found, type=' . $char);
            }

            $callable = FunctionMethod::getCurrentCallable();

            $inner_name = trim(array_shift($parameter_names), '*& ');
            if (empty($inner_name)) {
                return true;
            }
            if (($temp = strpos($inner_name, '.')) !== false) {
                $inner_name = substr($inner_name, 0, $temp);
            }
            if ($char == 's') {
                // string parameter has a following length parameter
                array_shift($parameter_names);
            }

            $optional = $index >= $callable->getNumberOfRequiredParameters();
            if ($index < $callable->getNumberOfParameters()) {
                $reflection = $callable->getParameters()[$index++];
                $parameter = Parameter::fromPhpReflection($reflection);
            } else {
                // not proclaimed parameter
                $parameter = new Parameter($inner_name, null, null, $index++);
            }

            if (!$parameter->getType() && $type_map[$char]) {
                $parameter->setType($type_map[$char]);
            }

            FunctionMethod::setInnerParameters($parameter, $inner_name, $optional);
        }

        return true;
    }
}