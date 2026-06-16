<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class TravelersHand extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 13,
            type: StateType::ACTIVE_PLAYER,
            transitions: [
                'next' => Penalty::class,
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
            return $this->actPlayFromHand((string)$pendingIds);
        }

        $args = $this->getArgs();
        $busId = (int)$args['bus']['card_id'];
        
        // 1. Check if ANY boarding is possible with ACTIVE bus
        $canBoardActive = false;
        foreach ($args['passengers'] as $p) {
            if ($this->game->getBoardingError($busId, $p->id) === null) {
                $canBoardActive = true; break;
            }
        }
        
        // 2. Check if WR passengers can board ACTIVE bus
        if (!$canBoardActive) {
            $wrPassengers = $this->game->cards->getCardsInLocation('waiting_room');
            foreach ($wrPassengers as $p) {
                if ($this->game->getBoardingError($busId, $p->id) === null) {
                    $canBoardActive = true; break;
                }
            }
        }

        if ($canBoardActive) return; // Action is possible

        // 3. If NO boarding possible with active bus, check if ANY action was possible at start of turn
        // (This logic mirrors ActiveBus::getArgs canTriggerEnd)
        $handCards = $this->game->cards->getCardsInLocation('hand', $activePlayerId);
        $handBuses = array_filter($handCards, fn($c) => $c->type === 'bus');
        
        $platformBuses = $this->game->cards->getCardsInLocation('platform');
        // FILTER: Stalled buses are not productive
        $activePlatformBuses = array_filter($platformBuses, fn($b) => !$this->game->isBusStalled($b->id));

        $garageEmpty = (int)$this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location='garage'") === 0 && 
                      (int)$this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_type='bus' AND card_location='deck'") === 0;
        $roundEndTriggered = (int) $this->game->getGameStateValue('round_end_trigger') !== 0;

        if (!$garageEmpty && !$roundEndTriggered) return; // Garage not empty and not in final turns, penalty is normal

        if (!empty($handBuses)) return; // Could have placed a bus

        // Check if ANY boarding is possible with ANY platform bus (not just the active one)
        $canBoardAny = false;
        $wrPassengers = $this->game->cards->getCardsInLocation('waiting_room');
        foreach ($platformBuses as $altBus) {
            // Check hand passengers
            foreach ($handCards as $p) {
                if ($p->type === 'passenger' && $this->game->getBoardingError($altBus->id, $p->id) === null) {
                    $canBoardAny = true; break 2;
                }
            }
            // Check WR passengers
            foreach ($wrPassengers as $p) {
                if ($this->game->getBoardingError($altBus->id, $p->id) === null) {
                    $canBoardAny = true; break 2;
                }
            }
        }
        
        if ($canBoardAny) return; // Could have made a productive move

        // Check if Star ability could have been used
        $canUseStar = false;
        $handStars = array_filter($handCards, fn($c) => $c->type === 'passenger' && $c->type_arg % 10 === PASSENGER_STAR);
        if (!empty($handStars)) {
            $garageBuses = $this->game->cards->getCardsInLocation('garage');
            $departedBuses = $this->game->cards->getCardsInLocation('departed_buses');
            $allCallables = array_merge($garageBuses, $departedBuses);
            foreach ($handStars as $star) {
                $starColor = (int) floor($star->type_arg / 10);
                foreach ($allCallables as $bus) {
                    $busColor = (int) floor($bus->type_arg / 10);
                    if ($starColor === $busColor) {
                        $canUseStar = true; break 2;
                    }
                }
            }
        }

        if ($canUseStar) return; // Could have used a Star to bring a bus

        // 4. If we are here, NO productive action was possible this turn. Trigger End of Round or Skip.
        $this->bga->notify->all("message", clienttranslate('${player_name} has no more passengers to play and skips turn.'), [
            'player_name' => $this->game->getPlayerNameById($activePlayerId)
        ]);

        $playerCount = (int) $this->game->getPlayersNumber();
        $currentTrigger = (int) $this->game->getGameStateValue('round_end_trigger');
        
        if ($currentTrigger === 0) {
            $this->game->setGameStateValue('round_end_trigger', $activePlayerId);
            $this->game->setGameStateValue('remaining_turns', $playerCount - 1);

            $this->bga->notify->all("roundEndTriggered", clienttranslate('${player_name} cannot play and the Garage is empty! One last turn for everyone else.'), [
                "player_id" => $activePlayerId,
                "player_name" => $this->game->getPlayerNameById($activePlayerId),
                "hand" => $this->game->cards->getCardsInLocation('hand', $activePlayerId),
            ]);
        } else {
            $this->bga->notify->all("message", clienttranslate('${player_name} also skips their turn as they have no valid moves.'), [
                "player_id" => $activePlayerId,
                "player_name" => $this->game->getPlayerNameById($activePlayerId),
            ]);
        }

        return NextPlayer::class;
    }

    public function getArgs(): array
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $hand = $this->game->cards->getCardsInLocation('hand', $playerId);
        $passengers = array_values(array_filter($hand, fn($c) => $c->type === 'passenger'));

        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        $sql = "SELECT * FROM card WHERE card_id = $busId";
        $bus = $this->game->getObjectFromDB($sql);

        return [
            "passengers" => $passengers,
            "bus" => $bus,
        ];
    }    

    #[PossibleAction]
    public function actPlayFromHand(string $passengerIds)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $busId = (int) $this->game->getGameStateValue('active_bus_id');
        
        $ids = array_filter(array_map('intval', explode(',', $passengerIds)));
        
        $successfullyBoardedCount = 0;
        $corrupterTriggered = false;
        $departState = null;
        $this->game->setGameStateValue('pending_boarding_passengers', 0); // Clear initially

        foreach ($ids as $index => $pId) {
            // Check for Surfer capacity logic (if it doesn't fit, it returns to hand)
            $sql = "SELECT * FROM card WHERE card_id = $pId";
            $pRow = $this->game->getObjectFromDB($sql);
            if ((int)$pRow['card_type_arg'] % 10 == PASSENGER_SURFER) {
                if ($this->game->getBoardingError($busId, $pId) !== null) {
                    $this->bga->notify->all("passengerReturns", clienttranslate('${player_name}\'s Surfer doesn\'t fit and returns to hand'), [
                        "player_id" => $playerId,
                        "player_name" => $this->game->getPlayerNameById($playerId),
                        "passenger_id" => $pId,
                    ]);
                    continue; // Doesn't board
                }
            }

            $trigger = $this->game->boardPassenger($busId, $pId);
            $card = $this->game->cards->getCard($pId); // Formatted object

            if ($trigger === "corrupter") {
                $corrupterTriggered = true;
                $this->game->setGameStateValue('corrupter_swaps_pending', (int)$this->game->getGameStateValue('corrupter_swaps_pending') + 1);
                $this->game->setGameStateValue('last_corrupter_id', $pId);
                
                // SAVE REST OF QUEUE
                $restOfQueue = array_slice($ids, $index + 1);
                if (!empty($restOfQueue)) {
                    $this->game->setGameStateValue('pending_boarding_passengers', implode(',', $restOfQueue));
                }
            }
            
            $successfullyBoardedCount++;

            $this->bga->notify->all("passengerPlayed", clienttranslate('${player_name} plays a passenger from hand'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
                "passenger_id" => $pId,
                "passenger_data" => $card, // Consistent key names
                "bus_id" => $busId,
            ]);

            $departState = $this->game->checkBusDeparture($busId);
            
            if ($corrupterTriggered) break;
            if ($departState !== null) break;
        }
        
        if ($corrupterTriggered) {
            $this->game->setGameStateValue('bus_departed_during_corrupter', $departState === 'refill' ? 1 : ($departState === 'drunkard' ? 2 : 0));
            $this->game->setGameStateValue('corrupter_origin_state', 2); // 2 = TravelersHand
            return CorrupterSwap::class;
        }

        if ($departState === 'drunkard') {
            return DrunkardChoice::class;
        }
        if ($departState === 'refill') {
            return RefillHand::class;
        }

        if ($successfullyBoardedCount === 0 && !empty($ids)) {
            return TravelersHand::class; // Stay here if they tried but failed
        }

        return Penalty::class;
    }

    #[PossibleAction]
    public function actSkip()
    {
        return Penalty::class;
    }

    function zombie(int $playerId) {
        return $this->actSkip();
    }
}
