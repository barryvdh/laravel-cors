<?php

namespace Fruitcake\Cors\Tests;

use Fruitcake\Cors\HandleCors;
use Fruitcake\Cors\HandleCorsGroup;
use Fruitcake\Cors\HandlePreflight;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

class GroupMiddlewareTest extends AbstractTest
{
    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Add the middleware
        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);
        $kernel->prependMiddleware(HandlePreflight::class);

        // Add the middleware
        Route::group([], __DIR__ . '/routes/web.php');
        Route::middleware([HandleCorsGroup::class])->group(__DIR__ . '/routes/api.php');

        parent::getEnvironmentSetUp($app);
    }
}
