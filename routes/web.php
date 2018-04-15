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
        Route::post('/games', 'GameController@createGame');
        Route::delete('/games', 'GameController@removeAllGames');
        Route::put('/games/{id}/play', 'GameController@playGame');
        Route::put('/games/{id}/watch', 'GameController@watchGame');
});

//--------------WEB SOCKETS----------------



Route::group(['middleware' => ['ajaxAuth']], function () {
    Route::get('/websocket/message/joinRoomPlay', 'WebSocket\JoinRoomPlay@handleMessage');
    Route::get('/websocket/message/joinRoomTables', 'WebSocket\JoinRoomTables@handleMessage');
    Route::get('/websocket/message/broadcastGameCreated', 'WebSocket\BroadcastGameCreated@handleMessage');
    Route::get('/websocket/message/broadcastPlayerJoined', 'WebSocket\BroadcastPlayerJoined@handleMessage');
    Route::get('/websocket/message/userPick', 'WebSocket\UserPick@handleMessage');
    Route::get('/websocket/message/userMove', 'WebSocket\UserMove@handleMessage');
    Route::get('/websocket/message/sendChatMessage', 'WebSocket\SendChatMessage@handleMessage');
});

Route::get('/websocket/open', 'WebSocketController@onOpen');
Route::get('/websocket/close', 'WebSocketController@onClose');
Route::get('/websocket/error', 'WebSocketController@onError');