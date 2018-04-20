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





//ajax requests requiring a cookie with session id
Route::group(['middleware' => ['ajaxAuth']], function () {
        Route::delete('/games', 'GameController@removeAllGames');


});

//--------------WEB SOCKETS----------------



Route::group(['middleware' => ['ajaxAuth']], function () {
    Route::get('/websocket/message/joinRoomPlay', 'WebSocket\JoinRoomPlay@handleMessage');
    Route::get('/websocket/message/joinRoomTables', 'WebSocket\JoinRoomTables@handleMessage');
    Route::get('/websocket/message/userPick', 'WebSocket\UserPick@handleMessage');
    Route::get('/websocket/message/userMove', 'WebSocket\UserMove@handleMessage');
    Route::get('/websocket/message/sendChatMessage', 'WebSocket\SendChatMessage@handleMessage');
    Route::get('/websocket/message/createGame', 'WebSocket\CreateGame@handleMessage');
    Route::get('/websocket/message/playGame', 'WebSocket\PlayGame@handleMessage');
    Route::get('/websocket/message/watchGame', 'WebSocket\WatchGame@handleMessage');
    Route::get('/websocket/message/exitGame', 'WebSocket\ExitGame@handleMessage');
    Route::get('/websocket/message/confirmPlaying', 'WebSocket\ConfirmPlaying@handleMessage');
    Route::get('/websocket/message/surrender', 'WebSocket\Surrender@handleMessage');
    Route::get('/websocket/open', 'WebSocketController@onOpen');
});


Route::get('/websocket/close', 'WebSocketController@onClose');
Route::get('/websocket/error', 'WebSocketController@onError');