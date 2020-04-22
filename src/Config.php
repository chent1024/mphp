<?php

namespace mphp;

class Config
{

    //store config
    protected static $config = [];

    /**
     *  try to load config file
     *
     * @param $key
     * @param null $default
     * @return mixed
     */
    protected static function _loadConfig($key, $default = null)
    {
        //split key: app.debug //config/app.php
        $keys = explode('.', $key);
        $filename = $keys[0];

        //if config.file loaded but not found config
        if (isset(self::$config[$filename])) {
            return $default;
        }

        $file = root_path('config' . DIRECTORY_SEPARATOR . $filename . '.php');
        if (file_exists($file)) {
            $config = require_once $file;
            if (is_array($config)) {
                self::set($config, null, $filename . '.', false);
            }

            self::set($filename, $config, '', false);
            if (isset(self::$config[$key])) {
                return self::$config[$key];
            }
        }

        return $default;
    }

    /**
     * get config
     *
     * @param string $key
     * @param null $default
     * @return array|mixed|null
     */
    public static function get($key = '', $default = null)
    {
        if (!$key) {
            return self::$config;
        }

        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }

        return self::_loadConfig($key, $default);
    }

    /**
     * set config
     *
     * @param $key
     * @param string $value
     * @param string $prefix
     * @param bool $replace
     * @return bool|mixed|string
     */
    public static function set($key, $value = '', $prefix = '', $replace = true)
    {
        if (is_array($key)) {
            foreach ($key as $kk => $vv) {
                if (!$replace && isset(self::$config[$prefix . $kk])) {
                    continue;
                }

                self::$config[$prefix . $kk] = $vv;
            }

            return true;
        }

        if (!$replace && isset(self::$config[$prefix . $key])) {
            return self::$config[$prefix . $key];
        }

        return self::$config[$prefix . $key] = $value;
    }
}

