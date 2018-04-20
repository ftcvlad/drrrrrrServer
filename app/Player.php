<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 19/04/2018
 * Time: 18:19
 */

namespace App;


abstract class PlayerStatuses
{
    const waiting = 0;
    const playing = 1;
    const confirming = 2;
    const ready = 3;
    const suggestingDraw = 4;
    const resolvingDrawOffer = 5;
}

class Player
{

    public $id;
    public $username;
    public $currentStatus;

    function __construct($username, $userId, $currentStatus)
    {
        $this->username = $username;
        $this->id = $userId;
        $this->currentStatus = $currentStatus;
    }


}