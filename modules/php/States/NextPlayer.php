<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\Games\BUUUUUUUUS\Game;

class NextPlayer extends \Bga\GameFramework\States\GameState
{

    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 90,
            type: StateType::GAME,
            updateGameProgression: true,
        );
    }

    /**
     * Game state action, example content.
     *
     * The onEnteringState method of state `nextPlayer` is called everytime the current game state is set to `nextPlayer`.
     */
    function onEnteringState(int $activePlayerId) {

        // 1. Cleanup Waiting Room: slide down (Pushes all cards towards row 4)
        $moved = false;
        for ($col = 1; $col <= 2; $col++) {
            // Repeat up to 3 times to ensure all gaps are closed
            for ($i = 0; $i < 3; $i++) {
                for ($row = 3; $row >= 1; $row--) {
                    $pos = $col * 10 + $row;
                    $sql = "SELECT * FROM card WHERE card_location = 'waiting_room' AND card_location_arg = $pos";
                    $card = $this->game->getObjectFromDB($sql);
                    if ($card) {
                        $nextPos = $pos + 1;
                        $sqlBelow = "SELECT count(*) FROM card WHERE card_location = 'waiting_room' AND card_location_arg = $nextPos";
                        if ((int)$this->game->getUniqueValueFromDB($sqlBelow) === 0) {
                            $this->game->cards->moveCard((int)$card['card_id'], 'waiting_room', $nextPos);
                            $moved = true;
                        }
                    }
                }
            }
        }
        
        if ($moved) {
            $this->game->bga->notify->all("waitingRoomSlid", clienttranslate('The passengers in the Waiting Room move forward in line'), [
                'waitingRoom' => array_values($this->game->cards->getCardsInLocation('waiting_room'))
            ]);
        }

        // 2. Refill Waiting Room
        $refilledCards = [];
        for ($col = 1; $col <= 2; $col++) {
            for ($row = 1; $row <= 4; $row++) {
                $pos = $col * 10 + $row;
                $sql = "SELECT count(*) FROM card WHERE card_location = 'waiting_room' AND card_location_arg = $pos";
                if ((int)$this->game->getUniqueValueFromDB($sql) === 0) {
                    $newCard = $this->game->cards->pickCard('passenger', 'waiting_room', $pos);
                    if ($newCard) {
                        $refilledCards[] = $newCard;
                    }
                }
            }
        }
        
        if (!empty($refilledCards)) {
            $this->game->bga->notify->all("waitingRoomRefilled", clienttranslate('New passengers enter the Waiting Room queue'), [
                'newCards' => $refilledCards
            ]);
        }

        // 4. Baby Priority
        $this->game->applyBabyPriority();

        // 3. Refill Garage
        $newGarageBuses = [];
        for ($i = 1; $i <= 4; $i++) {
            $sql = "SELECT count(*) FROM card WHERE card_location = 'garage' AND card_location_arg = $i";
            if ($this->game->getUniqueValueFromDB($sql) == 0) {
                $bus = $this->game->cards->pickCard('bus', 'garage', $i);
                if ($bus) {
                    $newGarageBuses[] = $bus;
                }
            }
        }
        if (!empty($newGarageBuses)) {
            $this->game->bga->notify->all("garageRefilled", clienttranslate('The Garage is refilled with new buses'), [
                'newBuses' => $newGarageBuses
            ]);
        }

        // Give some extra time to the active player when he completed an action
        $this->game->giveExtraTime($activePlayerId);

        // --- ROUND END DETECTION ---
        $roundEnd = false;

        // 1. Check if current player has empty hand (Instant end)
        $handCount = (int)$this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='hand' AND card_location_arg=$activePlayerId");
        if ($handCount === 0) {
            $this->game->bga->notify->all("message", clienttranslate('${player_name} has no more cards in hand! The round ends immediately.'), [
                'player_name' => $this->game->getPlayerNameById($activePlayerId)
            ]);
            $roundEnd = true;
        }

        // 2. Check if round end was already triggered
        $roundEndTrigger = (int)$this->game->getGameStateValue('round_end_trigger');
        if ($roundEndTrigger != 0) {
            $remainingTurns = (int)$this->game->getGameStateValue('remaining_turns');
            if ($remainingTurns === 0) {
                $roundEnd = true;
            } else {
                $this->game->setGameStateValue('remaining_turns', $remainingTurns - 1);
            }
        }

        if ($roundEnd) {
            return EndScore::class;
        }

        $this->game->activeNextPlayer();
        return ActiveBus::class;
    }
}