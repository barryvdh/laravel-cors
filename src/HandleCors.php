<?php

namespace Fruitcake\Cors;

use Closure;
use Asm89\Stack\CorsService;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
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
        // Check if we're dealing with CORS and if we should handle it
        if (! $this->shouldRun($request)) {
            return $next($request);
        }

        // For Preflight, return the Preflight response
        if ($this->cors->isPreflightRequest($request)) {
            $response = $this->cors->handlePreflightRequest($request);

            $this->cors->varyHeader($response, 'Access-Control-Request-Method');

            return $response;
        }

        // Add the headers on the Request Handled event as fallback in case of exceptions
        if (class_exists(RequestHandled::class) && $this->container->bound('events')) {
            $this->container->make('events')->listen(RequestHandled::class, function (RequestHandled $event) {
                $this->addHeaders($event->request, $event->response);
            });
        }

        $this->myConfigureAllowedOrigin($request);

        // Handle the request
        $response = $next($request);

        if ($request->getMethod() === 'OPTIONS') {
            $this->cors->varyHeader($response, 'Access-Control-Request-Method');
        }

//        return $this->addHeaders($request, $response);
        return $response;
    }

    /**
     * Add the headers to the Response, if they don't exist yet.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    protected function addHeaders(Request $request, Response $response): Response
    {
        header_remove('Access-Control-Allow-Origin'); // MTM: To prevent multiple headers
        if (! $response->headers->has('Access-Control-Allow-Origin')) {
            // Add the CORS headers to the Response
            $response = $this->cors->addActualRequestHeaders($response, $request);
        }

        return $response;
    }

    /**
     * MTM hotfix
     *
     * @param Response $response
     * @param Request $request
     */
    private function myConfigureAllowedOrigin(Request $request)
    {
        if ($this->cors->options['allowedOrigins'] === true && ! $this->cors->options['supportsCredentials']) {
            // Safe+cacheable, allow everything
            header('Access-Control-Allow-Origin: *', true);
        } elseif ($this->cors->isSingleOriginAllowed()) {
            // Single origins can be safely set
            header('Access-Control-Allow-Origin: ' . array_values($this->cors->options['allowedOrigins'])[0], true);
        } else {
            // For dynamic headers, check the origin first
            if ($this->cors->isOriginAllowed($request)) {
                header('Access-Control-Allow-Origin: ' . $request->headers->get('Origin'), true);
            }

//            $this->cors->varyHeader($response, 'Origin');
        }
    }

    /**
     * Determine if the request has a URI that should pass through the CORS flow.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldRun(Request $request): bool
    {
        return $this->isMatchingPath($request);
    }

    /**
     * The the path from the config, to see if the CORS Service should run
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function isMatchingPath(Request $request): bool
    {
        // Get the paths from the config or the middleware
        $paths = $this->container['config']->get('cors.paths', []);

        foreach ($paths as $path) {
            if ($path !== '/') {
                $path = trim($path, '/');
            }

            if ($request->fullUrlIs($path) || $request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
