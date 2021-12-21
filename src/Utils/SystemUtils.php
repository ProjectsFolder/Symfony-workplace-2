<?php

namespace App\Utils;

use Doctrine\Common\Annotations\AnnotationReader;
use ReflectionClass;
use ReflectionException;

class SystemUtils
{
    private static $cache = [];

    /**
     * Получает список аннотаций, связанных с заданным классом или методом класса.
     *
     * @param string $class
     * @param string|null $method
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public static function getAnnotationsByClass(string $class, string $method = null): array
    {
        $key = "getAnnotationsByClass:$class:$method";
        if (!empty(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $annotations = self::getDetailedAnnotationsByClass($class, $method);
        $annotations = array_merge($annotations['class'] ?? [], $annotations['method'] ?? []);
        $annotations = array_map(function ($annotation) {
            return get_class($annotation);
        }, $annotations);
        self::$cache[$key] = array_unique($annotations);

        return self::$cache[$key];
    }

    /**
     * Получает подробный список аннотаций, связанных с заданным классом или методом класса.
     *
     * @param string $class
     * @param string|null $method
     *
     * @return array
     *
     * @throws ReflectionException
     */
    public static function getDetailedAnnotationsByClass(string $class, string $method = null): array
    {
        $key = "getDetailedAnnotationsByClass:$class:$method";
        if (!empty(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $annotations = [];
        if (class_exists($class)) {
            $reader = new AnnotationReader();
            $reflection = new ReflectionClass($class);
            $annotations['class'] = $reader->getClassAnnotations($reflection);
            if (!empty($method) && $reflection->hasMethod($method)) {
                $method = $reflection->getMethod($method);
                $annotations['method'] = $reader->getMethodAnnotations($method);
            }
        }
        self::$cache[$key] = $annotations;

        return self::$cache[$key];
    }
}
