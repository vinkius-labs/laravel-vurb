<?php

use Illuminate\Support\Facades\Route;
use Vinkius\Vurb\Http\Controllers\VurbBridgeController;
use Vinkius\Vurb\Http\Middleware\ValidateVurbToken;

Route::prefix(config('vurb.bridge.prefix', '/_vurb'))
    ->middleware(ValidateVurbToken::class)
    ->group(function () {
        Route::post('/execute/{toolName}/handle', [VurbBridgeController::class, 'execute'])->where('toolName', '[a-zA-Z0-9_.]+');
        Route::post('/schema/refresh', [VurbBridgeController::class, 'refreshSchema']);
        Route::post('/state/transition', [VurbBridgeController::class, 'fsmTransition']);
        Route::get('/health', [VurbBridgeController::class, 'health']);
    });
