<?php
namespace app\core;

class Router {
    private $routes = [];
    private $params = [];
    
    public function add($method, $route, $controller, $action) {
        $this->routes[] = [
            'method' => $method,
            'route' => $route,
            'controller' => $controller,
            'action' => $action
        ];
    }
    
    public function dispatch($method, $uri) {
        $uri = parse_url($uri, PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            $pattern = $this->convertRouteToRegex($route['route']);
            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->params = $matches;
                
                $controller = "app\\controllers\\" . $route['controller'];
                $action = $route['action'];
                
                $controllerInstance = new $controller();
                return call_user_func_array([$controllerInstance, $action], $this->params);
            }
        }
        
        throw new \Exception('Route not found', 404);
    }
    
    private function convertRouteToRegex($route) {
        $route = preg_replace('/\{([a-zA-Z]+)\}/', '(?P<$1>[^/]+)', $route);
        return '#^' . $route . '$#';
    }
    
    public function getParams() {
        return $this->params;
    }
} 