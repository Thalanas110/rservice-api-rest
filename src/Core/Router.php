<?php

namespace Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/:[a-zA-Z0-9_]+/', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                array_shift($matches); // Remove full match

                // Execute Middleware
                foreach ($route['middleware'] as $mw) {
                    if (is_callable($mw)) {
                        call_user_func($mw, $request);
                    } elseif (class_exists($mw)) {
                        // Assume middleware class has a static handle method or is invokable. 
                        // For simplicity, let's assume it's a static method 'handle' or invokable object.
                        // Or better, let middleware be a callable.
                        $mw_instance = new $mw();
                        $mw_instance->handle($request);
                    }
                }

                // Execute Handler
                $handler = $route['handler'];
                if (is_array($handler)) {
                    $controllerName = $handler[0];
                    $actionName = $handler[1];
                    $controller = new $controllerName();
                    call_user_func_array([$controller, $actionName], [$request, ...$matches]);
                } else {
                    call_user_func_array($handler, [$request, ...$matches]);
                }
                return;
            }
        }

        Response::error('Not Found', 404);
    }
}
