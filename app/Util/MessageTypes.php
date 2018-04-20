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
    const CONFIRM_PLAYING = "confirmPlaying";
    const BROADCAST_CONFIRM_PLAYING = "broadcastConfirmPlaying";

    const BROADCAST_PARTICIPANTS_CHANGED_to_table = "broadcastParticipantsChangedToTable";
    const BROADCAST_PARTICIPANTS_CHANGED_to_tables = "broadcastParticipantsChangedToTables";
    const BROADCAST_GAME_STARTED = "broadcastGameStarted";
    const BROADCAST_GAME_FINISHED = "broadcastGameFinished";

    const USER_MOVE = "userMove";
    const USER_PICK = "userPick";

    const SEND_CHAT_MESSAGE = "sendChatMessage";

    const EXIT_GAME = "exitGame";
    const BROADCAST_TABLE_REMOVED = "broadcastTableRemoved";

    const SURRENDER = "surrender";
    const BROADCAST_SURRENDER = "broadcastSurrender";

}