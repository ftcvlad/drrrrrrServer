<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 19/04/2018
 * Time: 18:36
 */

namespace App;


class Watcher
{
    public $id;
    public $username;

    function __construct($username, $userId)
    {
        $this->username = $username;
        $this->id = $userId;
    }
}