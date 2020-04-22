<?php

namespace mphp;
class Application
{
    protected static $app;
    protected $loader;
    protected $dispatcher;
    protected $params = [];

    public function __construct()
    {
        $this->checkDefined();
        $this->loader = new Loader();
        $this->dispatcher = new Dispatcher();
        $this->init();
    }

    public function __call($name, $params)
    {
        $callback = $this->dispatcher->get($name);
        if (is_callable($callback)) {
            return $this->dispatcher->run($name, $params);
        }

        if (!$this->loader->get($name)) {
            throw new \Exception("{$name} must be a mapped method.");
        }

        $shared = (!empty($params)) ? (bool)$params[0] : true;
        return $this->loader->load($name, $shared);
    }

    public static function app()
    {
        static $initialized = false;
        if (!$initialized) {
            self::$app = new static();
            $initialized = true;
        }

        return self::$app;
    }

    public function init()
    {
        $this->loader->register('log', '\mphp\Log');
        $this->loader->register('config', '\mphp\Config');
        $this->loader->register('request', '\mphp\Request');
        $this->loader->register('response', '\mphp\Response');
        $this->loader->register('router', '\mphp\Router');
        $this->loader->register('view', '\mphp\View', [], function ($view) {
            $view->path = APP_PATH . DIRECTORY_SEPARATOR . 'views';
        });

        $this->set('root_path', ROOT_PATH);
        $this->set('app_path', APP_PATH);

        $methods = [
            'start', 'stop',
            'route', 'halt', 'error', 'notFound', 'redirect',
            'render', 'json', 'jsonp'
        ];
        foreach ($methods as $name) {
            $this->dispatcher->set($name, array($this, '_' . $name));
        }
    }

    protected function checkDefined()
    {
        if (!defined('APP_PATH')) {
            throw new \Exception("APP_PATH must be a defined.");
        }

        if (!defined('ROOT_PATH')) {
            throw new \Exception("ROOT_PATH must be a defined.");
        }
    }

    protected function before($name, $callback)
    {
        $this->dispatcher->hook($name, 'before', $callback);
    }

    protected function after($name, $callback)
    {
        $this->dispatcher->hook($name, 'after', $callback);
    }

    public function set($key, $val)
    {
        if (is_array($key) || is_object($key)) {
            foreach ($key as $k => $v) {
                $this->params[$k] = $v;
            }
        } else {
            $this->params[$key] = $val;
        }
    }

    public function get($key = null)
    {
        if (is_null($key)) {
            return $this->params;
        }

        return $this->params[$key] ?? null;
    }

    public function has($key)
    {
        return isset($this->params[$key]);
    }

    public function clearParams($key = null)
    {
        if (is_null($key)) {
            $this->params = [];
        } else {
            unset($this->params[$key]);
        }
    }

    public function handleError($errno, $errstr, $errfile, $errline)
    {
        if ($errno & error_reporting()) {
            $this->log()->err($errstr, [$errno, $errfile, $errline]);
            throw new \ErrorException($errstr, $errno, 0, $errfile, $errline);
        }
    }

    public function handleException($e)
    {
        $msg = '<h1>500 Internal Server Error</h1>';
        if (config('app.debug')) {
            $msg .= sprintf('<h3>%s (%s)</h3><pre>%s</pre>',
                $e->getMessage(),
                $e->getCode(),
                $e->getTraceAsString()
            );
        }

        try {
            $this->log()->err($e->getMessage(), [$e->getCode(), $e->getTraceAsString()]);
            $this->response()
                ->clear()
                ->status(500)
                ->write($msg)
                ->send();
        } catch (\Throwable $t) { // PHP 7.0+
            $this->log()->err($msg, [$t->getCode(), $t->getTraceAsString()]);
            exit($msg);
        } catch (\Exception $e) { // PHP < 7
            $this->log()->err($msg, [$e->getCode(), $e->getTraceAsString()]);
            exit($msg);
        }
    }

    public function map($name, $callback)
    {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }

        $this->dispatcher->set($name, $callback);
    }

    public function register($name, $class, array $params = [], $callback = null)
    {
        if (method_exists($this, $name)) {
            throw new \Exception('Cannot override an existing framework method.');
        }

        $this->loader->register($name, $class, $params, $callback);
    }

    public function _start()
    {
        error_reporting(-1);
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        if (!config('app.debug')) {
            ini_set('display_errors', 'Off');
        }

        $this->log()->init();
        $dispatched = false;
        $self = $this;
        $request = $this->request();
        $response = $this->response();
        $router = $this->router();

        // Allow filters to run
        $this->after('start', function () use ($self) {
            $self->stop();
        });

        // Flush any existing output
        if (ob_get_length() > 0) {
            $response->write(ob_get_clean());
        }

        // Enable output buffering
        ob_start();

        // Route the request
        while ($route = $router->route($request)) {
            $params = array_values($route->params);

            // Add route info to the parameter list
            if ($route->pass) {
                $params[] = $route;
            }

            // Call route handler
            $continue = $this->dispatcher->execute($route->callback, $params);

            $dispatched = true;
            if (!$continue) {
                break;
            }

            $router->next();
            $dispatched = false;
        }

        if (!$dispatched) {
            $this->notFound();
        }
    }

    public function _stop($code = null)
    {
        $response = $this->response();
        if (!$response->sent()) {
            if ($code !== null) {
                $response->status($code);
            }
            $response->write(ob_get_clean());
            $response->send();
        }
    }

    public function _route($pattern, $callback, $pass_route = false)
    {
        $this->router()->map($pattern, $callback, $pass_route);
    }

    public function _halt($code = 200, $message = '')
    {
        $this->response()
            ->clear()
            ->status($code)
            ->write($message)
            ->send();
        exit();
    }

    public function _notFound()
    {
        $this->response()
            ->clear()
            ->status(404)
            ->write('<h1>404 Not Found</h1>')
            ->send();
    }

    /**
     * Redirects the current request to another URL.
     *
     * @param string $url URL
     * @param int $code HTTP status code
     */
    public function _redirect($url, $code = 303)
    {
        $base = config('app.base_url');
        if ($base === null) {
            $base = $this->request()->base;
        }

        // Append base url to redirect url
        if ($base != '/' && strpos($url, '://') === false) {
            $url = $base . preg_replace('#/+#', '/', '/' . $url);
        }

        $this->response()
            ->clear()
            ->status($code)
            ->header('Location', $url)
            ->send();
    }

    public function _render($file, $data = null, $key = null)
    {
        if ($key !== null) {
            $this->view()->set($key, $this->view()->fetch($file, $data));
        } else {
            $this->view()->render($file, $data);
        }
    }

    public function _json($data, $code = 200, $encode = true, $charset = 'utf-8', $option = 0)
    {
        $json = ($encode) ? json_encode($data, $option) : $data;
        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/json; charset=' . $charset)
            ->write($json)
            ->send();
    }

    public function _jsonp($data, $param = 'jsonp', $code = 200, $encode = true, $charset = 'utf-8', $option = 0)
    {
        $json = ($encode) ? json_encode($data, $option) : $data;
        $callback = $this->request()->query[$param];
        $this->response()
            ->status($code)
            ->header('Content-Type', 'application/javascript; charset=' . $charset)
            ->write($callback . '(' . $json . ');')
            ->send();
    }
}
