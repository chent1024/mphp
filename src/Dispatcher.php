<?php

namespace mphp;
class Dispatcher
{
    protected $events = [];
    protected $filters = [];

    public function run($name, array $params = [])
    {
        $output = '';
        // Run pre-filters
        if (!empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }
        // Run requested method
        $output = $this->execute($this->get($name), $params);

        // Run post-filters
        if (!empty($this->filters[$name]['after'])) {
            $this->filter($this->filters[$name]['after'], $params, $output);
        }

        return $output;
    }

    public function set($name, $callback)
    {
        $this->events[$name] = $callback;
    }

    public function get($name)
    {
        return isset($this->events[$name]) ? $this->events[$name] : null;
    }

    public function has($name)
    {
        return isset($this->events[$name]);
    }

    public function clear($name = null)
    {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);
        } else {
            $this->events = [];
            $this->filters = [];
        }
    }

    public function hook($name, $type, $callback)
    {
        $this->filters[$name][$type][] = $callback;
    }

    public function filter($filters, &$params, &$output)
    {
        $args = array(&$params, &$output);
        foreach ($filters as $callback) {
            $continue = $this->execute($callback, $args);
            if ($continue === false) {
                break;
            }
        }
    }

    public static function execute($callback, array &$params = [])
    {
        if (is_callable($callback)) {
            return is_array($callback) ? self::invokeMethod($callback, $params) : self::callFunction($callback, $params);
        }

        throw new \Exception('Invalid callback specified.');
    }


    public static function callFunction($func, array &$params = [])
    {
        return call_user_func_array($func, $params);
    }

    public static function invokeMethod($func, array &$params = [])
    {
        list($class, $method) = $func;
        $instance = is_object($class);
        if ($instance) {
            return self::callFunction($func, $params);
        }

        $cls = new $class;
        return $cls->$method(...$params);
    }

    public function reset()
    {
        $this->events = [];
        $this->filters = [];
    }
}
