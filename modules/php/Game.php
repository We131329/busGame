<?php
/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * BUUUUUUUUS implementation : © Erickbond <perickpor@hotmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * Game.php
 *
 * This is the main file for your game logic.
 *
 * In this PHP file, you are going to defines the rules of the game.
 */
declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS;

require_once('Managers/StackManager.php');

use Bga\Games\BUUUUUUUUS\Managers\CardManager;
use Bga\Games\BUUUUUUUUS\Managers\StackManager;
use Bga\Games\BUUUUUUUUS\States\ActiveBus;
use Bga\Games\BUUUUUUUUS\States\WaitingRoom;
use Bga\Games\BUUUUUUUUS\States\CorrupterSwap;
use Bga\Games\BUUUUUUUUS\States\DrunkardChoice;
use Bga\Games\BUUUUUUUUS\States\TravelersHand;
use Bga\Games\BUUUUUUUUS\States\Penalty;
use Bga\Games\BUUUUUUUUS\States\RefillHand;
use Bga\Games\BUUUUUUUUS\States\NextPlayer;
use Bga\GameFramework\Components\Counters\PlayerCounter;

class Game extends \Bga\GameFramework\Table
{
    public CardManager $cards;

    public array $colors;
    public array $passenger_types;
    public array $bus_capacities;

    public function __construct()
    {
        parent::__construct();

        include(__DIR__ . '/../../material.inc.php');

        $this->cards = new CardManager($this);

        $this->initGameStateLabels([
            'round' => 10,
            'active_bus_id' => 11,
            'boarding_happened' => 12, // For Penalty phase
            'corrupter_swaps_pending' => 13,
            'bus_departed_during_corrupter' => 14,
            'corrupter_origin_state' => 15,
            'drunkard_bus_id' => 16,
            'last_corrupter_id' => 17,
            'round_end_trigger' => 18,
            'remaining_turns' => 19,
            'pending_boarding_passengers' => 20,
        ]);
    }

    /**
     * Debug: Simulate end of round conditions.
     * 1 bus left in garage, garage deck empty.
     */
    public function debug_testRoundEnd()
    {
        // 1. Empty the bus deck
        static::DbQuery("UPDATE card SET card_location='none' WHERE card_type='bus' AND card_location='deck'");
        
        // 2. Clear Garage and leave only 1 bus
        static::DbQuery("UPDATE card SET card_location='none' WHERE card_location='garage'");
        $this->cards->pickCard('bus', 'garage', 1); // Note: this might fail if deck was emptied.
        // Better: move one from 'none' back
        $bus = $this->getObjectFromDB("SELECT card_id FROM card WHERE card_type='bus' AND card_location='none' LIMIT 1");
        if ($bus) {
            $this->cards->moveCard((int)$bus['card_id'], 'garage', 1);
        }

        $this->bga->notify->all("message", clienttranslate('DEBUG: Round end conditions simulated (Garage almost empty)'), []);
    }

    /**
     * Compute and return the current game progression.
     */
    public function getGameProgression()
    {
        $round = (int) $this->getGameStateValue('round');
        $roundBase = ($round - 1) * 50; // 0 for Round 1, 50 for Round 2

        $roundEndTrigger = (int) $this->getGameStateValue('round_end_trigger');
        if ($roundEndTrigger !== 0) {
            // Extra turns phase
            $remainingTurns = (int) $this->getGameStateValue('remaining_turns');
            $playerCount = (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player");
            $extraTurnsTotal = $playerCount - 1;
            if ($extraTurnsTotal <= 0) $extraTurnsTotal = 1;
            
            $fraction = 1.0 - ($remainingTurns / $extraTurnsTotal);
            if ($fraction > 1.0) $fraction = 1.0;
            if ($fraction < 0.0) $fraction = 0.0;
            
            $progression = $roundBase + 45 + ($fraction * 5);
        } else {
            // Standard phase based on remaining buses in deck/garage
            $playerCount = (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM player");
            $startingSupply = 16 - ($playerCount * 2);
            if ($startingSupply <= 0) $startingSupply = 10;
            
            $busesInSupply = (int) $this->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_type = 'bus' AND card_location IN ('deck', 'garage')");
            $busesDrawn = $startingSupply - $busesInSupply;
            if ($busesDrawn < 0) $busesDrawn = 0;
            
            $fraction = $busesDrawn / $startingSupply;
            if ($fraction > 1.0) $fraction = 1.0;
            
            $progression = $roundBase + ($fraction * 45);
        }

        return (int) round($progression);
    }

    protected function getAllDatas(int $currentPlayerId): array
    {
        $result = [];

        // Get information about players.
        $result["players"] = $this->getCollectionFromDb(
            "SELECT `player_id` AS `id`, `player_score` AS `score`, `player_unhappy_points` AS `unhappy_points` FROM `player`"
        );

        // Cards for the current player
        $result["hand"] = array_values($this->cards->getCardsInLocation('hand', $currentPlayerId));

        // Garage
        $result["garage"] = array_values($this->cards->getCardsInLocation('garage'));

        // Waiting Room
        $result["waiting_room"] = array_values($this->cards->getCardsInLocation('waiting_room'));

        // Platform (Buses and Passengers inside them)
        $result["platform_buses"] = array_values($this->cards->getCardsInLocation('platform'));
        $result["platform_passengers"] = array_values($this->cards->getCardsInLocation('in_bus'));

        // Other zones for the UI
        $result["passengers_zone"] = array_values($this->cards->getCardsInLocation('passengers'));
        $result["unhappies_zone"] = array_values($this->cards->getCardsInLocation('unhappies'));
        $result["departed_buses"] = array_values($this->cards->getCardsInLocation('departed_buses'));

        $result["passenger_types"] = $this->passenger_types;
        $result["bus_capacities"] = $this->bus_capacities;

        return $result;
    }

    /**
     * This method is called only once, when a new game is launched.
     */
    protected function setupNewGame($players, $options = [])
    {
        // Set the colors of the players
        $gameinfos = $this->getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        foreach ($players as $player_id => $player) {
            $query_values[] = vsprintf("(%s, '%s', '%s')", [
                $player_id,
                array_shift($default_colors),
                addslashes($player["player_name"]),
            ]);
        }

        static::DbQuery(
            sprintf(
                "INSERT INTO `player` (`player_id`, `player_color`, `player_name`) VALUES %s",
                implode(",", $query_values)
            )
        );

        $this->reattributeColorsBasedOnPreferences($players, $gameinfos["player_colors"]);
        $this->reloadPlayersBasicInfos();

        $this->bga->tableStats->init([], 0);
        $this->bga->playerStats->init([
            "passengers_boarded",
            "passengers_departed",
            "unhappy_passengers",
            "happy_lovers",
            "unhappy_lovers",
            "star_abilities_activated",
            "hand_passengers_round1",
            "hand_passengers_round2",
            "round1_points",
            "round2_points"
        ], 0);

        // 1. Create Cards
        $cards = [];
        // Buses: 1x Cap 3, 2x Cap 4, 1x Cap 5 per color
        foreach ([COLOR_BLUE, COLOR_RED, COLOR_GREEN, COLOR_YELLOW] as $color) {
            // Cap 3
            $cards[] = ['type' => 'bus', 'type_arg' => $color * 10 + 3, 'location' => 'deck', 'location_arg' => 0];
            // Cap 4
            $cards[] = ['type' => 'bus', 'type_arg' => $color * 10 + 4, 'location' => 'deck', 'location_arg' => 0];
            $cards[] = ['type' => 'bus', 'type_arg' => $color * 10 + 4, 'location' => 'deck', 'location_arg' => 0];
            // Cap 5
            $cards[] = ['type' => 'bus', 'type_arg' => $color * 10 + 5, 'location' => 'deck', 'location_arg' => 0];
        }

        // Passengers: 5 Anonymous + 9 Specials (with 2 Lovers) per color
        foreach ([COLOR_BLUE, COLOR_RED, COLOR_GREEN, COLOR_YELLOW] as $color) {
            // 5 Anonymous
            for ($i = 0; $i < 5; $i++) {
                $cards[] = ['type' => 'passenger', 'type_arg' => $color * 10 + PASSENGER_ANONYMOUS, 'location' => 'deck', 'location_arg' => 0];
            }
            // Specials
            $specials = [
                PASSENGER_SURFER,
                PASSENGER_LOVERS, PASSENGER_LOVERS,
                PASSENGER_GENERAL,
                PASSENGER_STAR,
                PASSENGER_DRUNKARD,
                PASSENGER_BACKPACKER,
                PASSENGER_CORRUPTER,
                PASSENGER_BABY
            ];
            foreach ($specials as $ability) {
                $cards[] = ['type' => 'passenger', 'type_arg' => $color * 10 + $ability, 'location' => 'deck', 'location_arg' => 0];
            }
        }
        $this->cards->createCards($cards);

        // 2. Shuffle
        $this->cards->shuffle('bus');
        $this->cards->shuffle('passenger');

        // 3. Deal to players
        foreach (array_keys($players) as $player_id) {
            $pId = (int) $player_id;
            // 2 Buses
            for ($i = 0; $i < 2; $i++) {
                $this->cards->pickCard('bus', 'hand', $pId);
            }
            // 7 Passengers
            for ($i = 0; $i < 7; $i++) {
                $this->cards->pickCard('passenger', 'hand', $pId);
            }
        }

        // 4. Fill Garage (4 buses)
        for ($i = 1; $i <= 4; $i++) {
            $this->cards->pickCard('bus', 'garage', $i);
        }

        // 5. Fill Waiting Room (8 passengers: 2 cols x 4 rows)
        // Col 1: positions 11, 12, 13, 14
        // Col 2: positions 21, 22, 23, 24
        for ($col = 1; $col <= 2; $col++) {
            for ($row = 1; $row <= 4; $row++) {
                $this->cards->pickCard('passenger', 'waiting_room', $col * 10 + $row);
            }
        }

        $this->applyBabyPriority();

        $this->setGameStateInitialValue('round', 1);

        $this->activeNextPlayer();

        return ActiveBus::class;
    }

    public function applyBabyPriority()
    {
        for ($col = 1; $col <= 2; $col++) {
            $sql = "SELECT * FROM card WHERE card_location = 'waiting_room' AND card_location_arg LIKE '{$col}%' ORDER BY card_location_arg ASC";
            $columnCards = $this->getCollectionFromDb($sql);
            
            $babies = [];
            $others = [];
            foreach ($columnCards as $cRow) {
                if ((int)$cRow['card_type_arg'] % 10 == PASSENGER_BABY) {
                    $babies[] = $cRow;
                } else {
                    $others[] = $cRow;
                }
            }
            
            if (!empty($babies)) {
                // Re-arrange: babies at the bottom (row 4, 3, ...), others above
                $newOrder = array_merge($others, $babies);
                foreach ($newOrder as $index => $cRow) {
                    $newRow = 4 - (count($newOrder) - 1 - $index);
                    $newPos = $col * 10 + $newRow;
                    if ((int)$cRow['card_location_arg'] != $newPos) {
                        $this->cards->moveCard((int)$cRow['card_id'], 'waiting_room', $newPos);
                    }
                }
                
                $this->bga->notify->all("babiesPrioritized", clienttranslate('Babies move to the front of the queue in column ${col}'), [
                    'col' => $col,
                    'waitingRoom' => array_values($this->cards->getCardsInLocation('waiting_room'))
                ]);
            }
        }
    }

    /**
     * Example of debug function.
     * Here, jump to a state you want to test (by default, jump to next player state)
     * You can trigger it on Studio using the Debug button on the right of the top bar.
     */
    public function isBusStalled(int $busId): bool
    {
        $sql = "SELECT * FROM card WHERE card_id = $busId";
        $busRow = $this->getObjectFromDB($sql);
        if (!$busRow) return false;

        $busCapacity = (int) $busRow['card_type_arg'] % 10;
        $currentOccupancy = $this->getBusOccupancy($busId);

        if ($currentOccupancy < $busCapacity) return false;

        $passengers = $this->cards->getCardsInLocation('in_bus', $busId);
        $hasGeneral = false;
        $loverCount = 0;
        foreach ($passengers as $p) {
            $ability = $p->type_arg % 10;
            if ($ability == PASSENGER_GENERAL) $hasGeneral = true;
            if ($ability == PASSENGER_LOVERS) $loverCount++;
        }

        return !$hasGeneral && ($loverCount % 2 != 0);
    }

    public function getBoardingError(int $busId, int $passengerId): ?string
    {
        $sql = "SELECT * FROM card WHERE card_id = $busId";
        $busRow = $this->getObjectFromDB($sql);
        if (!$busRow) return "Bus not found";

        $sql = "SELECT * FROM card WHERE card_id = $passengerId";
        $pRow = $this->getObjectFromDB($sql);
        if (!$pRow) return "Passenger not found";

        $busColor = (int) floor((int)$busRow['card_type_arg'] / 10);
        $pColor = (int) floor((int)$pRow['card_type_arg'] / 10);
        $pAbility = (int)$pRow['card_type_arg'] % 10;

        // Color check: matching color OR Backpacker (6) is wild
        if ($pColor !== $busColor && $pAbility !== PASSENGER_BACKPACKER) {
            return "Wrong color";
        }

        $busCapacity = (int) $busRow['card_type_arg'] % 10;
        $currentOccupancy = $this->getBusOccupancy($busId);
        $pCapacity = $this->passenger_types[$pAbility]['capacity'];

        // General (3) can board even if the bus is full
        if ($pAbility !== PASSENGER_GENERAL && ($currentOccupancy + $pCapacity > $busCapacity)) {
            return "Bus is full";
        }

        return null;
    }

    public function boardPassenger(int $busId, int $passengerId): ?string
    {
        $error = $this->getBoardingError($busId, $passengerId);
        if ($error) return $error;

        $this->cards->moveCard($passengerId, 'in_bus', $busId);
        $this->setGameStateValue('boarding_happened', 1);

        $activePlayerId = (int)$this->getActivePlayerId();
        $this->bga->playerStats->inc('passengers_boarded', 1, $activePlayerId);

        $sql = "SELECT * FROM card WHERE card_id = $passengerId";
        $p = $this->getObjectFromDB($sql);
        if ((int)$p['card_type_arg'] % 10 == PASSENGER_CORRUPTER) {
            return "corrupter";
        }

        return null;
    }

    public function getBusOccupancy(int $busId): int
    {
        $passengers = $this->cards->getCardsInLocation('in_bus', $busId);
        $occupancy = 0;
        foreach ($passengers as $p) {
            $ability = $p->type_arg % 10;
            $occupancy += $this->passenger_types[$ability]['capacity'];
        }
        return $occupancy;
    }

    public function checkBusDeparture(int $busId): ?string
    {
        $sql = "SELECT * FROM card WHERE card_id = $busId";
        $busRow = $this->getObjectFromDB($sql);
        if (!$busRow) return null;

        $busCapacity = (int) $busRow['card_type_arg'] % 10;
        $currentOccupancy = $this->getBusOccupancy($busId);

        // Check for General (instant depart)
        $passengers = $this->cards->getCardsInLocation('in_bus', $busId);
        $hasGeneral = false;
        foreach ($passengers as $p) {
            if ($p->type_arg % 10 == PASSENGER_GENERAL) {
                $hasGeneral = true;
                break;
            }
        }

        if ($hasGeneral || $currentOccupancy >= $busCapacity) {
            // Lovers Stall check (unless General is present, who clears them)
            if (!$hasGeneral && $currentOccupancy >= $busCapacity) {
                $loverCount = 0;
                foreach ($passengers as $p) {
                    if ($p->type_arg % 10 == PASSENGER_LOVERS) {
                        $loverCount++;
                    }
                }
                if ($loverCount % 2 != 0) {
                    $this->bga->notify->all("busStalled", clienttranslate('The bus is full but a Lover is missing their partner! It stalls.'), [
                        "bus_id" => $busId,
                    ]);
                    return null;
                }
            }

            return $this->departBus($busId, $hasGeneral);
        }

        return null;
    }

    public function departBus(int $busId, bool $isGeneral = false): string
    {
        $passengers = $this->cards->getCardsInLocation('in_bus', $busId);
        $activePlayerId = (int) $this->getActivePlayerId();

        // Special logic for General: Lovers go to Unhappies
        if ($isGeneral) {
            foreach ($passengers as $key => $p) {
                if ($p->type_arg % 10 == PASSENGER_LOVERS) {
                    $this->cards->moveCard($p->id, 'unhappies', $activePlayerId);
                    unset($passengers[$key]);
                    
                    $this->bga->notify->all("passengerToUnhappies", clienttranslate('Lovers are unhappy because of the General!'), [
                        "passenger_id" => $p->id,
                        "player_id" => $activePlayerId,
                    ]);
                }
            }
        }

        // Check for Drunkard
        $hasDrunkard = false;
        foreach ($passengers as $p) {
            if ($p->type_arg % 10 == PASSENGER_DRUNKARD) {
                $hasDrunkard = true;
                break;
            }
        }

        if ($hasDrunkard) {
            $this->setGameStateValue('drunkard_bus_id', $busId);
            return 'drunkard';
        }
        
        // Move remaining passengers to 'passengers' zone
        foreach ($passengers as $p) {
            $this->cards->moveCard($p->id, 'passengers', $activePlayerId);
            $this->bga->playerStats->inc('passengers_departed', 1, $activePlayerId);
        }

        // Move bus to departed_buses
        $this->cards->moveCard($busId, 'departed_buses', $activePlayerId);

        // --- RE-ARRANGE PLATFORM ---
        $platformBuses = $this->cards->getCardsInLocation('platform');
        foreach ($platformBuses as $index => $bus) {
            $this->cards->moveCard($bus->id, 'platform', $index + 1);
        }

        $this->bga->notify->all("busDeparted", clienttranslate('A bus has departed!'), [
            "player_id" => $activePlayerId,
            "bus_id" => $busId,
            "passenger_ids" => array_map(fn($p) => $p->id, array_values($passengers)),
            "platform_buses" => array_values($this->cards->getCardsInLocation('platform')),
        ]);

        return 'refill';
    }

    public function debug_goToState(int $state = 3) {
        $this->gamestate->jumpToState($state);
    }

    /**
     * Another example of debug function, to easily test the zombie code.
     */
    public function debug_playOneMove() {
        $this->bga->debug->playUntil(fn(int $count) => $count == 1);
    }

    /*
    Another example of debug function, to easily create situations you want to test.
    Here, put a card you want to test in your hand (assuming you use the Deck component).

    public function debug_setCardInHand(int $cardType, int $playerId) {
        $card = array_values($this->cards->getCardsOfType($cardType))[0];
        $this->cards->moveCard($card['id'], 'hand', $playerId);
    }
    */
}
