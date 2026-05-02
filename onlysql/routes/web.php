<?php

use App\Http\Controllers\OrderPaginationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/users/{user}/orders/offset', [OrderPaginationController::class, 'getOrdersOffset']);
Route::get('/users/{user}/orders/keyset', [OrderPaginationController::class, 'getOrdersKeyset']);
