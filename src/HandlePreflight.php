<?php

namespace Fruitcake\Cors;

use Closure;
use Asm89\Stack\CorsService;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class HandlePreflight
{
    /** @var CorsService $cors */
    protected $cors;

    /** @var \Illuminate\Contracts\Container\Container $container */
    protected $container;

    public function __construct(CorsService $cors, Container $container)
    {
        $this->cors = $cors;
        $this->container = $container;
    }

    /**
     * Handle an incoming request. Based on Asm89\Stack\Cors by asm89
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        if ($this->shouldRun($request)) {
            return $this->cors->handlePreflightRequest($request);
        }

        return $next($request);
    }

    /**
     * Check if it's an CORS request, skip the paths check
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldRun(Request $request): bool
    {
        if (!$this->cors->isPreflightRequest($request)) {
            return false;
        }

        /** @var \Illuminate\Foundation\Http\Kernel $kernel */
        $kernel = $this->container->make(Kernel::class);

        // When the HandleCors middleware is not attached globally, add the PreflightCheck
        if ($kernel->hasMiddleware(HandleCors::class)) {
            return true;
        }

        foreach ($this->findMatchingMiddleware($request) as $middleware) {
            if (
                in_array($middleware, [
                HandleCors::class,
                HandleCorsGroup::class,
                ])
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find a matching route
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function findMatchingMiddleware($request)
    {
        $requestMethod = strtoupper($request->headers->get('Access-Control-Request-Method'));

        /** @var Router $router */
        $router = $this->container['router'];
        [$fallbacks, $routes] = collect($router->getRoutes()->get($requestMethod))->partition(function ($route) {
            return $route->isFallback;
        });

        $route = $routes->merge($fallbacks)->first(function ($value) use ($request) {
            return $value->matches($request, false);
        });

        if ($route) {
            return $route->gatherMiddleware();
        }

        return [];
    }
}
