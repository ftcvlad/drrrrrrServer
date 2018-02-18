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
    const JOIN_ROOM = "joinRoom";
    const JOINED_ROOM = "joinedRoom";
    const ERROR = "error";
    const BROADCAST_GAME_CREATED = "broadcastGameCreated";
}