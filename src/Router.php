<?php
namespace mphp;

class Router {
    /**
     * Mapped routes.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * Pointer to current route.
     *
     * @var int
     */
    protected $index = 0;

    /**
     * Case sensitive matching.
     *
     * @var boolean
     */
    public $caseSensitive = false;

    /**
     * Gets mapped routes.
     *
     * @return array Array of routes
     */
    public function getRoutes() {
        return $this->routes;
    }

    /**
     * Clears all routes in the router.
     */
    public function clear() {
        $this->routes = [];
    }

    /**
     * Maps a URL pattern to a callback function.
     *
     * @param string $pattern URL pattern to match
     * @param callback $callback Callback function
     * @param boolean $pass_route Pass the matching route object to the callback
     */
    public function map($pattern, $callback, $pass_route = false) {
        $url = $pattern;
        $methods = ['*'];

        if (strpos($pattern, ' ') !== false) {
            list($method, $url) = explode(' ', trim($pattern), 2);
            $url = trim($url);
            $methods = explode('|', $method);
        }

        $this->routes[] = new Route($url, $callback, $methods, $pass_route);
    }

    /**
     * Routes the current request.
     *
     * @param Request $request Request object
     * @return Route|bool Matching route or false if no match
     */
    public function route(Request $request) {
        $urlDecoded = urldecode( $request->url );
        while ($route = $this->current()) {
            if ($route !== false
                && $route->matchMethod($request->method)
                && $route->matchUrl($urlDecoded, $this->caseSensitive)) {
                return $route;
            }

            $this->next();
        }

        return false;
    }

    /**
     * Gets the current route.
     *
     * @return Route
     */
    public function current() {
        return isset($this->routes[$this->index]) ? $this->routes[$this->index] : false;
    }

    /**
     * Gets the next route.
     *
     * @return Route
     */
    public function next() {
        $this->index++;
    }

    /**
     * Reset to the first route.
     */
    public  function reset() {
        $this->index = 0;
    }
}

