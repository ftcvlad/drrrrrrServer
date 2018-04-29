<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 19/04/2018
 * Time: 18:19
 */

namespace App;

class Player
{

    public $id;
    public $username;
    public $currentStatus;
    public $rating;
    public $playsWhite;

    function __construct($username, $userId, $currentStatus, $rating, $playsWhite)
    {
        $this->username = $username;
        $this->id = $userId;
        $this->currentStatus = $currentStatus;
        $this->rating = $rating;
        $this->playsWhite = $playsWhite;
    }


}