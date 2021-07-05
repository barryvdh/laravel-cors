<?php

use Illuminate\Support\Facades\Route;

Route::post('web/ping', function () {
    return 'PONG';
});