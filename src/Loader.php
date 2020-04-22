<?php

namespace mphp;

class Loader
{

    protected $classes = [];

    protected $instances = [];

    protected static $dirs = [];

    public function register($name, $class, array $params = [], $callback = null)
    {
        unset($this->instances[$name]);
        $this->classes[$name] = [$class, $params, $callback];
    }

    public function unregister($name)
    {
        unset($this->classes[$name]);
    }

    public function load($name, $shared = true)
    {
        $obj = null;
        if (isset($this->classes[$name])) {
            list($class, $params, $callback) = $this->classes[$name];

            $exists = isset($this->instances[$name]);
            if ($shared) {
                $obj = ($exists) ? $this->getInstance($name) : $this->newInstance($class, $params);

                if (!$exists) {
                    $this->instances[$name] = $obj;
                }
            } else {
                $obj = $this->newInstance($class, $params);
            }

            if ($callback && (!$shared || !$exists)) {
                $ref = [&$obj];
                call_user_func_array($callback, $ref);
            }
        }

        return $obj;
    }

    public function getInstance($name)
    {
        return isset($this->instances[$name]) ? $this->instances[$name] : null;
    }

    public function newInstance($class, array $params = [])
    {
        if (is_callable($class)) {
            return call_user_func_array($class, $params);
        }

        try {
            $refClass = new \ReflectionClass($class);
            return $refClass->newInstanceArgs($params);
        } catch (\ReflectionException $e) {
            throw new \Exception("Cannot instantiate {$class}", 0, $e);
        }
    }

    public function get($name)
    {
        return isset($this->classes[$name]) ? $this->classes[$name] : null;
    }

    public function reset()
    {
        $this->classes = [];
        $this->instances = [];
    }

    public static function autoload($enabled = true, $dirs = [])
    {
        if ($enabled) {
            spl_autoload_register(array(__CLASS__, 'loadClass'));
        } else {
            spl_autoload_unregister(array(__CLASS__, 'loadClass'));
        }

        if (!empty($dirs)) {
            self::addDirectory($dirs);
        }
    }

    public static function loadClass($class)
    {
        $class_file = str_replace(array('\\', '_'), '/', $class) . '.php';
        foreach (self::$dirs as $dir) {
            $file = $dir . '/' . $class_file;
            if (!file_exists($file)) {
                require $file;
                return;
            }
        }
    }

    public static function addDirectory($dir)
    {
        if (is_array($dir) || is_object($dir)) {
            foreach ($dir as $value) {
                self::addDirectory($value);
            }
            return;
        }

        if (is_string($dir) && !in_array($dir, self::$dirs)) {
            self::$dirs[] = $dir;
        }
    }
}
