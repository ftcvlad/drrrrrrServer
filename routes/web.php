<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/





Route::post('session', 'Auth\LoginController@login')->name('login');
Route::delete('session', 'Auth\LoginController@logout')->name('logout');
Route::post('users', 'Auth\RegisterController@register');

Route::get('/user', 'UserController@getUser');

Route::get('/home', 'HomeController@index')->name('home');



//ajax requests requiring a cookie with session id
Route::group(['middleware' => ['ajaxAuth']], function () {
        Route::post('/games', 'GameController@createGame');
});