<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;

Route::post('api/ping', function () {
    return 'PONG';
});

Route::put('api/ping',  function () {
    return 'PONG';
});

Route::post('api/error',  function () {
    abort(500);
});

Route::post('api/validation',  function (Request $request) {

    Validator::make($request->all(), [
        'name' => 'required',
    ])->validate();

    return 'ok';
});

Route::fallback(function () {
    abort(404);
});