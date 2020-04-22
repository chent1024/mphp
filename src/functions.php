<?php

use mphp\lib\Collection;
use mphp\Application;

if (!function_exists('app')) {
    function app()
    {
        return Application::app();
    }
}

if (!function_exists('root_path')) {
    function root_path($path = '')
    {
        $path = $path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $path;
        return app()->get('root_path') . $path;
    }
}

if (!function_exists('app_path')) {
    function app_path($path = '')
    {
        $path = $path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $path;
        return app()->get('app_path') . $path;
    }
}

if (!function_exists('public_path')) {
    function public_path($path = '')
    {
        $path = $path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : $path;
        return app()->get('root_path') . $path;
    }
}

if (!function_exists('view')) {
    function view($file = '', $data = null)
    {
        if (!$file) {
            return app()->view();
        }
        return app()->render($file, $data);
    }
}

if (!function_exists('request')) {
    function request()
    {
        $req = app()->request();
        if (!isset($req->all)) {
            $req->all = new Collection($_REQUEST);
            if (strpos($req->type, 'application/json') === 0) {
                $req->all->setData(array_merge($req->query->getData(), $req->data->getData()));
            }
        }
        return $req;
    }
}

if (!function_exists('all')) {
    function all($key = null, $default = null)
    {
        $all = request()->all;
        if (is_null($key)) {
            return $all;
        }

        return isset($all[$key]) ? $all[$key] : $default;
    }
}

if (!function_exists('input')) {
    function input($key, $default = null)
    {
        $data = request()->data;
        return isset($data[$key]) ? $data[$key] : $default;
    }
}

if (!function_exists('query')) {
    function query($key, $default = null)
    {
        $data = request()->query;
        return isset($data[$key]) ? $data[$key] : $default;
    }
}

if (!function_exists('redirect')) {
    function redirect($url, $status = 303)
    {
        app()->redirect($url, $status);
    }
}

if (!function_exists('cookie')) {
    function cookie($key, $default = null)
    {
        if (is_null($key)) {
            return $_COOKIE;
        }

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_COOKIE[$k] = $v;
            }
            return $_COOKIE;
        }

        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : $default;
    }
}

if (!function_exists('session')) {
    function session($key, $default = null)
    {
        if (session_status() == PHP_SESSION_NONE) {
            @session_start();
        }

        $ret = $_SESSION;
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $_SESSION[$k] = $v;
            }
        } else if ($key) {
            $ret = isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
        }

        session_write_close();
        return $ret;
    }
}

if (!function_exists('config')) {
    /**
     * get config from config file; eg:config('app.env')
     *
     * @param $key
     * @param null $default
     * @return array|bool|mixed|string|null
     */
    function config($key, $default = null)
    {
        if (is_array($key)) {
            return app()->config()->set($key);
        } else {
            return app()->config()->get($key, $default);
        }
    }
}

if (!function_exists('des_encrypt')) {
    /**
     * 加密数据
     *
     * @param $data
     * @return string
     */
    function des_encrypt($data)
    {
        if (!is_array($data)) {
            $data = [$data];
        }

        $str = json_encode($data);
        $secret = config('app.des_encrypt_key', '123456789123456789123456');
        $iv = config('app.des_secret_iv', '01234567');
        return openssl_encrypt($str, 'DES-EDE3-CBC', $secret, 0, $iv);
    }
}

if (!function_exists('des_decrypt')) {
    /**
     * 解密数据
     *
     * @param string $str
     * @return array
     */
    function des_decrypt($str)
    {
        $secret = config('app.des_encrypt_key', '123456789123456789123456');
        $iv = config('app.des_secret_iv', '01234567');
        $data = openssl_decrypt(trim($str), 'DES-EDE3-CBC', $secret, 0, $iv);
        return json_decode($data, true);
    }
}

if (!function_exists('json_return')) {
    /**
     * 返回json数据
     *
     * @param array $jsonArr
     * @param bool $encode
     * @return json
     */
    function json_return($jsonArr, $encode = false)
    {
        $json = [
            'status' => $jsonArr['status'] ?? 1,
            'info' => $jsonArr['info'] ?? 'ok',
            'data' => $jsonArr['data'] ?? [],
        ];
        if (!empty($jsonArr['root'])) {
            $json = array_merge($json, $jsonArr['root']);
        }

        if ($encode) {
            $json['data'] = des_encrypt($json['data']);
        }

        return app()->json($json);
    }
}

if (!function_exists('json_error')) {
    /**
     * 返回json错误信息
     *
     * @param $code int
     * @param string $msg
     * @return json
     */
    function json_error($code, $msg = '')
    {
        $err = [
            40320 => '请先登录',
            403 => '无权限',
            404 => '未找到数据',
            406 => '参数错误',
            500 => '服务错误，请重试~',
        ];
        if ($msg) {
            $error = $msg;
        } elseif (isset($err[$code])) {
            $error = $err[$code];
        } else {
            $error = '未知错误';
        }

        return json_return(['status' => 0, 'info' => $error, 'data' => []]);
    }
}

if (!function_exists('msubstr')) {
    /**
     * Sub string
     *
     * @param $str
     * @param int $start
     * @param $length
     * @param string $charset
     * @param string $suffix
     * @return false|string
     */
    function msubstr($str, $start = 0, $length, $charset = "utf-8", $suffix = '...')
    {
        if (function_exists("mb_substr")) {
            $result = mb_substr($str, $start, $length, $charset);
        } else if (function_exists('iconv_substr')) {
            $result = iconv_substr($str, $start, $length, $charset);
            if (false === $result) {
                $result = '';
            }
        } else {
            $regExp['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
            $regExp['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
            $regExp['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
            $regExp['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
            $match = [];
            preg_match_all($regExp[$charset], $str, $match);
            $result = join("", array_slice($match[0], $start, $length));
        }

        return $suffix && strlen($result) && $result != $str ? $result . $suffix : $result;
    }
}
