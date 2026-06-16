
-- ------
-- BGA Framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- buuuuuuuus implementation : © Erickbond <perickpor@hotmail.com>
--
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(32) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  `card_owner` int(11) DEFAULT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- Table to link passengers to buses on the platform
-- card_location will be 'bus_inner' and card_location_arg will be the bus_id
-- We can use the 'card' table for both buses and passengers.
-- When a bus is on the platform, its location is 'platform' and location_arg is its unique position or id.
-- When a passenger is in a bus, its location is 'in_bus' and location_arg is the bus's card_id.

ALTER TABLE `player` ADD `player_unhappy_points` INT(11) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `stack` (
  `stack_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `state_name` varchar(32) NOT NULL,
  `player_id` int(11) DEFAULT NULL,
  `args` text DEFAULT NULL,
  PRIMARY KEY (`stack_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
