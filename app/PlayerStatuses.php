<?php
/**
 * Created by PhpStorm.
 * User: Vlad
 * Date: 29/04/2018
 * Time: 10:29
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
    const disconnected = 6;
    const dropper = 7;

}