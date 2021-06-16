<?php

namespace Fruitcake\Cors;

use Asm89\Stack\CorsService;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Lumen\Application as LumenApplication;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Config;

class CorsServiceProvider extends BaseServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->configPath(), 'cors');

        $this->app->singleton(CorsService::class, function () {
            return new CorsService($this->corsOptions());
        });
    }

    /**
     * Register the config for publishing
     *
     */
    public function boot()
    {
        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$this->configPath() => config_path('cors.php')], 'cors');
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('cors');
        }

        // Add the headers on the Request Handled event as fallback in case of exceptions
        if (class_exists(RequestHandled::class) && $this->app->bound('events')) {
            $this->app->make('events')->listen(RequestHandled::class, function (RequestHandled $event) {
                $this->app->make(HandleCors::class)->onRequestHandled($event);
            });
        }
    }

    /**
     * Set the config path
     *
     * @return string
     */
    protected function configPath()
    {
        return __DIR__ . '/../config/cors.php';
    }

    /**
     * Get options for CorsService
     *
     * @return array
     */
    protected function corsOptions()
    {
        $config = Config::get('cors');

        if ($config['exposed_headers'] && !is_array($config['exposed_headers'])) {
            throw new \RuntimeException('CORS config `exposed_headers` should be `false` or an array');
        }

        foreach (['allowed_origins', 'allowed_origins_patterns',  'allowed_headers', 'allowed_methods'] as $key) {
            if (!is_array($config[$key])) {
                throw new \RuntimeException('CORS config `' . $key . '` should be an array');
            }
        }

        // Convert case to supported options
        $options = [
            'supportsCredentials' => $config['supports_credentials'],
            'allowedOrigins' => $config['allowed_origins'],
            'allowedOriginsPatterns' => $config['allowed_origins_patterns'],
            'allowedHeaders' => $config['allowed_headers'],
            'allowedMethods' => $config['allowed_methods'],
            'exposedHeaders' => $config['exposed_headers'],
            'maxAge' => $config['max_age'],
        ];

        // Transform wildcard pattern
        foreach ($options['allowedOrigins'] as $origin) {
            if (strpos($origin, '*') !== false) {
                $options['allowedOriginsPatterns'][] = $this->convertWildcardToPattern($origin);
            }
        }

        return $options;
    }

    /**
     * Create a pattern for a wildcard, based on Str::is() from Laravel
     *
     * @see https://github.com/laravel/framework/blob/5.5/src/Illuminate/Support/Str.php
     * @param string $pattern
     * @return string
     */
    protected function convertWildcardToPattern($pattern)
    {
        $pattern = preg_quote($pattern, '#');

        // Asterisks are translated into zero-or-more regular expression wildcards
        // to make it convenient to check if the strings starts with the given
        // pattern such as "library/*", making any string check convenient.
        $pattern = str_replace('\*', '.*', $pattern);

        return '#^' . $pattern . '\z#u';
    }
}
