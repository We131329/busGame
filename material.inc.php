<?php
/**
 *------
 * BGA Framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * buuuuuuuus implementation : © Erickbond <perickpor@hotmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * material.inc.php
 *
 * buuuuuuuus game material description
 *
 */

if (!defined('COLOR_BLUE')) {
    define('COLOR_BLUE', 1);
    define('COLOR_RED', 2);
    define('COLOR_GREEN', 3);
    define('COLOR_YELLOW', 4);
}

if (!defined('PASSENGER_ANONYMOUS')) {
    define('PASSENGER_ANONYMOUS', 0);
    define('PASSENGER_SURFER', 1);
    define('PASSENGER_LOVERS', 2);
    define('PASSENGER_GENERAL', 3);
    define('PASSENGER_STAR', 4);
    define('PASSENGER_DRUNKARD', 5);
    define('PASSENGER_BACKPACKER', 6);
    define('PASSENGER_CORRUPTER', 7);
    define('PASSENGER_BABY', 8);
}

$this->colors = [
    COLOR_BLUE => [
        'name' => clienttranslate('Blue'),
        'nametr' => clienttranslate('Blue'),
        'color' => '0000ff',
    ],
    COLOR_RED => [
        'name' => clienttranslate('Red'),
        'nametr' => clienttranslate('Red'),
        'color' => 'ff0000',
    ],
    COLOR_GREEN => [
        'name' => clienttranslate('Green'),
        'nametr' => clienttranslate('Green'),
        'color' => '00ff00',
    ],
    COLOR_YELLOW => [
        'name' => clienttranslate('Yellow'),
        'nametr' => clienttranslate('Yellow'),
        'color' => 'ffff00',
    ],
];

$this->passenger_types = [
    PASSENGER_ANONYMOUS => [
        'name' => clienttranslate('Anonymous'),
        'capacity' => 1,
        'description' => clienttranslate('No special skill.'),
    ],
    PASSENGER_SURFER => [
        'name' => clienttranslate('The Surfer'),
        'capacity' => 2,
        'description' => clienttranslate('Occupies 2 spaces. Cannot board if only 1 space left.'),
    ],
    PASSENGER_LOVERS => [
        'name' => clienttranslate('The Lovers'),
        'capacity' => 1,
        'description' => clienttranslate('Must travel in pairs. If the bus is full but only one lover is in it, it stalls.'),
    ],
    PASSENGER_GENERAL => [
        'name' => clienttranslate('The General'),
        'capacity' => 1,
        'description' => clienttranslate('Causes the bus to depart immediately. If a Lover is alone on the bus, they go to the Unhappies (a pair stays safe).'),
    ],
    PASSENGER_STAR => [
        'name' => clienttranslate('The Star'),
        'capacity' => 1,
        'description' => clienttranslate('Can call a bus from Garage or other players at turn start.'),
    ],
    PASSENGER_DRUNKARD => [
        'name' => clienttranslate('The Drunkard'),
        'capacity' => 1,
        'description' => clienttranslate('When departing, 2 other passengers go to Unhappies.'),
    ],
    PASSENGER_BACKPACKER => [
        'name' => clienttranslate('The Backpacker'),
        'capacity' => 1,
        'description' => clienttranslate('Can board any bus color.'),
    ],
    PASSENGER_CORRUPTER => [
        'name' => clienttranslate('The Corrupter'),
        'capacity' => 1,
        'description' => clienttranslate('Swap cards from hand with Waiting Room or Platform.'),
    ],
    PASSENGER_BABY => [
        'name' => clienttranslate('The Baby'),
        'capacity' => 1,
        'description' => clienttranslate('Always prioritized in the Waiting Room (moves to row 4).'),
    ],
];

$this->bus_capacities = [3, 4, 5];
