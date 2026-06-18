<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class WaitingRoom extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 12,
            type: StateType::ACTIVE_PLAYER,
            transitions: [
                'next' => TravelersHand::class,
                'depart' => RefillHand::class,
                'corrupter' => CorrupterSwap::class,
                'drunkard' => DrunkardChoice::class,
            ]
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        $pendingIds = $this->game->getGameStateValue('pending_boarding_passengers');
        if (!empty($pendingIds)) {
            // Resume boarding
            return $this->actBoardPassengers((string)$pendingIds);
        }

        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        if (!$this->hasEligiblePassengers($busId)) {
            $this->bga->notify->all("message", clienttranslate('There are no passengers in the Waiting Room that can board the current bus.'), []);
            return TravelersHand::class;
        }
    }

    public function getArgs(): array
    {
        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        $sql = "SELECT * FROM card WHERE card_id = $busId";
        $busRow = $this->game->getObjectFromDB($sql);
        
        $waitingRoom = $this->game->cards->getCardsInLocation('waiting_room');

        return [
            "bus" => $busRow,
            "waitingRoom" => array_values($waitingRoom),
        ];
    }    

    public function hasEligiblePassengers(int $busId): bool
    {
        for ($col = 1; $col <= 2; $col++) {
            $minPos = $col * 10;
            $maxPos = $col * 10 + 9;
            $sql = "SELECT card_id FROM card WHERE card_location = 'waiting_room' AND card_location_arg BETWEEN $minPos AND $maxPos ORDER BY card_location_arg DESC LIMIT 1";
            $pId = $this->game->getUniqueValueFromDB($sql);
            if ($pId && $this->game->getBoardingError($busId, (int)$pId) === null) {
                return true;
            }
        }
        return false;
    }

    #[PossibleAction]
    public function actBoardPassengers(string $passengerIds)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        
        $ids = array_filter(array_map('intval', explode(',', $passengerIds)));
        if (empty($ids)) {
             throw new UserException(clienttranslate("Select at least one passenger or click Skip"));
        }

        $this->game->setGameStateValue('pending_boarding_passengers', 0);

        // Use the order provided by the client (selection order)
        $queue = $ids;

        $corrupterTriggered = false;
        $departState = null;

        $successfullyBoardedCount = 0;
        $i = 0;
        while ($i < count($queue)) {
            $pId = $queue[$i];
            $i++;

            // 1. Check if eligible (must be at front of queue in its column)
            $sql = "SELECT * FROM card WHERE card_id = $pId";
            $p = $this->game->getObjectFromDB($sql);
            if (!$p || $p['card_location'] !== 'waiting_room') {
                continue;
            }
            
            $col = (int) floor((int)$p['card_location_arg'] / 10);
            $minPos = $col * 10;
            $maxPos = $col * 10 + 9;
            $sqlBottom = "SELECT card_id FROM card WHERE card_location = 'waiting_room' AND card_location_arg BETWEEN $minPos AND $maxPos ORDER BY card_location_arg DESC LIMIT 1";
            $bottomId = (int) $this->game->getUniqueValueFromDB($sqlBottom);
            
            if ($bottomId !== $pId) {
                // This card was behind someone else.
                $this->bga->notify->all("passengerStayed", clienttranslate('${player_name}\'s passenger is blocked and stays in Waiting Room'), [
                    "player_id" => $playerId,
                    "player_name" => $this->game->getPlayerNameById($playerId),
                    "passenger_id" => $pId,
                ]);
                continue;
            }

            // 2. Check if fits (includes color check now)
            $error = $this->game->getBoardingError($busId, $pId);
            if ($error !== null) {
                $this->bga->notify->all("passengerStayed", clienttranslate('${player_name}\'s passenger stays in Waiting Room: ${error_msg}'), [
                    "player_id" => $playerId,
                    "player_name" => $this->game->getPlayerNameById($playerId),
                    "passenger_id" => $pId,
                    "error_msg" => $error == "Wrong color" ? clienttranslate("Wrong color") : clienttranslate("Bus is full"),
                ]);
                continue;
            }

            // 3. Board
            $trigger = $this->game->boardPassenger($busId, $pId);
            if ($trigger === "corrupter") {
                $corrupterTriggered = true;
                $this->game->setGameStateValue('corrupter_swaps_pending', (int)$this->game->getGameStateValue('corrupter_swaps_pending') + 1);
                $this->game->setGameStateValue('last_corrupter_id', $pId);
                
                // SAVE REST OF QUEUE
                $restOfQueue = array_slice($queue, $i);
                if (!empty($restOfQueue)) {
                    $this->game->setGameStateValue('pending_boarding_passengers', implode(',', $restOfQueue));
                }
            }
            
            $successfullyBoardedCount++;

            $this->bga->notify->all("passengerBoarded", clienttranslate('${player_name} boards a passenger from Waiting Room'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
                "passenger_id" => $pId,
                "bus_id" => $busId,
            ]);

            // 4. Backpacker check: does a new BP become eligible?
            $sqlNextBottom = "SELECT * FROM card WHERE card_location = 'waiting_room' AND card_location_arg BETWEEN $minPos AND $maxPos ORDER BY card_location_arg DESC LIMIT 1";
            $nextBottom = $this->game->getObjectFromDB($sqlNextBottom);
            if ($nextBottom && (int)$nextBottom['card_type_arg'] % 10 == PASSENGER_BACKPACKER) {
                if (!in_array((int)$nextBottom['card_id'], $queue)) {
                    if ($this->game->getBoardingError($busId, (int)$nextBottom['card_id']) === null) {
                        $queue[] = (int)$nextBottom['card_id'];
                    }
                }
            }

            // 5. Check departure
            $departState = $this->game->checkBusDeparture($busId);
            if ($departState !== null) {
                break;
            }
        }
        
        if ($corrupterTriggered) {
            $this->game->setGameStateValue('bus_departed_during_corrupter', $departState === 'refill' ? 1 : ($departState === 'drunkard' ? 2 : 0));
            $this->game->setGameStateValue('corrupter_origin_state', 1); // 1 = WaitingRoom
            return CorrupterSwap::class;
        }

        if ($departState === 'drunkard') {
            return DrunkardChoice::class;
        }
        if ($departState === 'refill') {
            return RefillHand::class;
        }

        // Rule check: if no departure happened, check if there are still more mandatory boardings
        if ($this->hasEligiblePassengers($busId)) {
            return WaitingRoom::class;
        }

        if ($successfullyBoardedCount === 0 && !empty($ids)) {
            return WaitingRoom::class; // Stay here if they tried but failed
        }

        return TravelersHand::class;
    }

    #[PossibleAction]
    public function actSkip()
    {
        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        if ($this->hasEligiblePassengers($busId)) {
             throw new UserException(clienttranslate("You must board all eligible passengers from the Waiting Room"));
        }
        return TravelersHand::class;
    }

    function zombie(int $playerId) {
        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        $eligible = [];
        for ($col = 1; $col <= 2; $col++) {
            $minPos = $col * 10;
            $maxPos = $col * 10 + 9;
            $sql = "SELECT card_id FROM card WHERE card_location = 'waiting_room' AND card_location_arg BETWEEN $minPos AND $maxPos ORDER BY card_location_arg DESC LIMIT 1";
            $pId = $this->game->getUniqueValueFromDB($sql);
            if ($pId && $this->game->getBoardingError($busId, (int)$pId) === null) {
                $eligible[] = (int)$pId;
            }
        }
        if (!empty($eligible)) {
            return $this->actBoardPassengers(implode(',', $eligible));
        }
        return $this->actSkip();
    }
}
