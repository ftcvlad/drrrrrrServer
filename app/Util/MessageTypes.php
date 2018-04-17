<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 18/02/2018
 * Time: 17:25
 */

namespace App\Util;


abstract class MessageTypes
{
    const ERROR = "error";

    const JOIN_ROOM_PLAY = "joinRoomPlay";
    const JOIN_ROOM_TABLES = "joinRoomTables";

    const CREATE_GAME = "createGame";
    const BROADCAST_GAME_CREATED = "broadcastGameCreated";

    const PLAY_GAME = "playGame";
    const WATCH_GAME = "watchGame";
    const BROADCAST_PLAYER_JOINED_TO_TABLE = "broadcastPlayerJoinedToTable";
    const BROADCAST_PLAYER_JOINED_TO_TABLES = "broadcastPlayerJoinedToTables";

    const USER_MOVE = "userMove";
    const USER_PICK = "userPick";

    const SEND_CHAT_MESSAGE = "sendChatMessage";

}