<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::middleware('auth:api')->post('/posts', [UserController::class, 'get_posts']);
//Route::middleware('auth:api')->get('/posts', [UserController::class, 'get_posts']);
Route::middleware('auth:api')->post('/create/post', [UserController::class, 'create_post']);
Route::middleware('auth:api')->get('/my/posts', [UserController::class, 'my_posts']);
Route::middleware('auth:api')->delete('/delete/{id}', [UserController::class, 'delete_post']);
