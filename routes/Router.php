<?php
/**
 * routes/Router.php
 * Minimal front-controller router.
 * Matches METHOD + URI pattern and dispatches to the correct controller method.
 */

class Router {

    private array $routes = [];

    /** Register a route: e.g. add('GET', '/api/books', ...) */
    public function add(string $method, string $pattern, callable $handler): void {
        // Convert :param tokens to named capture groups
        $regex = preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern);
        $this->routes[] = [
            'method'  => strtoupper($method),
            'regex'   => "#^$regex$#",
            'handler' => $handler,
        ];
    }

    /** Shorthand helpers */
    public function get(string $p, callable $h): void    { $this->add('GET',    $p, $h); }
    public function post(string $p, callable $h): void   { $this->add('POST',   $p, $h); }
    public function put(string $p, callable $h): void    { $this->add('PUT',    $p, $h); }
    public function patch(string $p, callable $h): void  { $this->add('PATCH',  $p, $h); }
    public function delete(string $p, callable $h): void { $this->add('DELETE', $p, $h); }

    /** Dispatch the current request. */
    public function dispatch(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);
        // Support _method override for clients that can't send PATCH/DELETE
        if ($method === 'POST' && !empty($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        // Strip query string from path
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        // Remove script subfolder if API lives in a subdirectory
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . ltrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== '*') continue;
            if (!preg_match($route['regex'], $uri, $matches)) continue;

            // Extract named params
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            // Parse JSON body once
            $body = [];
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $raw  = file_get_contents('php://input');
                $body = json_decode($raw, true) ?? [];
            }

            ($route['handler'])($params, $body);
            return;
        }

        Response::error('Endpoint not found.', 404);
    }
}
