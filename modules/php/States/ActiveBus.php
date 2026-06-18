<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class ActiveBus extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 11,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        $args = $this->getArgs();
        if ($args['isStuck']) {
            if ($args['canTriggerEnd']) {
                $this->bga->notify->all("message", clienttranslate('${player_name} is stuck and has no more buses or possible moves. Skipping turn.'), [
                    'player_name' => $this->game->getPlayerNameById($activePlayerId)
                ]);
                return TravelersHand::class;
            } else {
                // Must take a penalty? Or just select a bus and fail?
                // In BUS, you must play a bus if you have one. If not, you take a penalty.
            }
        }
    }

    public function getArgs(): array
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $handCards = $this->game->cards->getCardsInLocation('hand', $playerId);
        
        $handBuses = array_filter($handCards, fn($c) => $c->type === 'bus');
        $handStars = array_filter($handCards, fn($c) => $c->type === 'passenger' && $c->type_arg % 10 === PASSENGER_STAR);
        $hasGeneral = !empty(array_filter($handCards, fn($c) => $c->type === 'passenger' && $c->type_arg % 10 === PASSENGER_GENERAL));

        $platformBuses = $this->game->cards->getCardsInLocation('platform');
        
        // Potential buses to call with Star: Garage, Departed
        $garageBuses = $this->game->cards->getCardsInLocation('garage');
        $departedBuses = $this->game->cards->getCardsInLocation('departed_buses');

        // Check if any passenger can board from hand or WR to ANY bus
        $handPassengers = array_filter($handCards, fn($c) => $c->type === 'passenger');
        $wrPassengers = $this->game->cards->getCardsInLocation('waiting_room');
        
        $canBoard = false;
        foreach ($platformBuses as $bus) {
            foreach ($handPassengers as $p) {
                if ($this->game->getBoardingError($bus->id, $p->id) === null) {
                    $canBoard = true; break 2;
                }
            }
            foreach ($wrPassengers as $p) {
                if ($this->game->getBoardingError($bus->id, $p->id) === null) {
                    $canBoard = true; break 2;
                }
            }
        }

        // A platform bus is "productive" if it's not stalled
        $productivePlatformBuses = array_filter($platformBuses, fn($b) => !$this->game->isBusStalled($b->id));
        
        // Check if any passenger can board from hand or WR to ANY bus
        $handPassengers = array_filter($handCards, fn($c) => $c->type === 'passenger');
        $wrPassengers = $this->game->cards->getCardsInLocation('waiting_room');
        
        $canBoard = false;
        foreach ($platformBuses as $bus) {
            foreach ($handPassengers as $p) {
                if ($this->game->getBoardingError($bus->id, $p->id) === null) {
                    $canBoard = true; break 2;
                }
            }
            foreach ($wrPassengers as $p) {
                if ($this->game->getBoardingError($bus->id, $p->id) === null) {
                    $canBoard = true; break 2;
                }
            }
        }

        // --- STAR ABILITY CHECK ---
        // A player is NOT stuck if they can use a Star to bring a new bus
        $canUseStarProductively = false;
        if (!empty($handStars)) {
            $allCallables = array_merge($garageBuses, $departedBuses);
            foreach ($handStars as $star) {
                $starColor = (int) floor($star->type_arg / 10);
                foreach ($allCallables as $bus) {
                    $busColor = (int) floor($bus->type_arg / 10);
                    if ($starColor === $busColor) {
                        $canUseStarProductively = true;
                        break 2;
                    }
                }
            }
        }

        $garageEmpty = empty($garageBuses) && (int)$this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_type='bus' AND card_location='deck'") === 0;
        $roundEndTriggered = (int) $this->game->getGameStateValue('round_end_trigger') !== 0;

        // Condition for being stuck: 
        // 1. No buses in hand to play.
        // 2. AND No passenger (hand/WR) can board ANY bus on the platform.
        // 3. AND Cannot use a Star to bring a matching bus.
        $isStuck = empty($handBuses) && !$canBoard && !$canUseStarProductively;
        
        // If stuck and either garage is empty OR round end is already triggered, allow skip/trigger
        $canTriggerEnd = $isStuck && ($garageEmpty || $roundEndTriggered);

        // Safety fallback: if no buses exist anywhere, player is definitely stuck
        if (empty($handBuses) && empty($platformBuses)) {
            $canTriggerEnd = true;
        }

        return [
            "handBuses" => array_values($handBuses),
            "platformBuses" => array_values($platformBuses),
            "handStars" => array_values($handStars),
            "garageBuses" => array_values($garageBuses),
            "departedBuses" => array_values($departedBuses),
            "hasGeneral" => $hasGeneral,
            "isStuck" => $isStuck,
            "canTriggerEnd" => $canTriggerEnd,
            "roundEndTriggeredBy" => (int) $this->game->getGameStateValue('round_end_trigger'),
        ];
    }    

    #[PossibleAction]
    public function actTriggerEnd()
    {
        $args = $this->getArgs();
        if (!$args['canTriggerEnd']) {
            throw new UserException(clienttranslate("You can still take actions"));
        }

        $playerId = (int) $this->game->getActivePlayerId();
        $playerCount = (int) $this->game->getPlayersNumber();
        
        $currentTrigger = (int) $this->game->getGameStateValue('round_end_trigger');
        if ($currentTrigger === 0) {
            $this->game->setGameStateValue('round_end_trigger', $playerId);
            $this->game->setGameStateValue('remaining_turns', $playerCount - 1);

            $this->bga->notify->all("roundEndTriggered", clienttranslate('${player_name} cannot play and the Garage is empty! One last turn for everyone else.'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
                "hand" => $this->game->cards->getCardsInLocation('hand', $playerId),
            ]);
        } else {
            $this->bga->notify->all("message", clienttranslate('${player_name} also skips their turn as they have no valid moves.'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
            ]);
        }

        return NextPlayer::class;
    }

    #[PossibleAction]
    public function actUseStar(int $starId, int $busId)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        
        // 1. Verify player has the Star in hand
        $star = $this->game->cards->getCardsInLocation('hand', $playerId);
        $star = array_filter($star, fn($c) => $c->id === $starId && $c->type_arg % 10 === PASSENGER_STAR);
        if (empty($star)) {
             throw new UserException(clienttranslate("You don't have a Star in hand"));
        }
        $star = reset($star);
        $starColor = (int) floor($star->type_arg / 10);
        
        // 2. Verify bus is in Garage or Departed Buses
        $sql = "SELECT * FROM card WHERE card_id = $busId";
        $bus = $this->game->getObjectFromDB($sql);
        if (!$bus || $bus['card_type'] !== 'bus') {
             throw new UserException(clienttranslate("Selected card is not a bus"));
        }
        
        $busColor = (int) floor((int)$bus['card_type_arg'] / 10);
        if ($busColor !== $starColor) {
             throw new UserException(clienttranslate("The Star can only call a bus of the same color"));
        }

        $validLocations = ['garage', 'departed_buses'];
        if (!in_array($bus['card_location'], $validLocations)) {
             throw new UserException(clienttranslate("You can only call a bus from the Garage or Departed buses"));
        }
        
        // 3. Move bus to Platform
        $platformCount = (int) $this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location = 'platform'");
        $this->game->cards->moveCard($busId, 'platform', $platformCount + 1);
        
        // 4. Move Star to inside the bus
        $this->game->cards->moveCard($starId, 'in_bus', $busId);
        
        // 5. Set as active bus
        $this->game->setGameStateValue('active_bus_id', $busId);
        $this->game->setGameStateValue('boarding_happened', 1); // Star has boarded! Avoid penalty.

        $this->game->bga->playerStats->inc('star_abilities_activated', 1, $playerId);

        $this->bga->notify->all("starUsed", clienttranslate('${player_name} uses a Star to activate a bus!'), [
            "player_id" => $playerId,
            "player_name" => $this->game->getPlayerNameById($playerId),
            "star_id" => $starId,
            "star_data" => $this->game->cards->getCard($starId),
            "bus_id" => $busId,
            "bus_data" => $this->game->cards->getCard($busId),
            "from_location" => $bus['card_location'],
        ]);
        
        return WaitingRoom::class;
    }

    #[PossibleAction]
    public function actSkipStar()
    {
        return ActiveBus::class;
    }

    #[PossibleAction]
    public function actSelectBus(int $cardId)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        
        $handCards = $this->game->cards->getCardsInLocation('hand', $playerId);
        $hasGeneral = !empty(array_filter($handCards, fn($c) => $c->type === 'passenger' && $c->type_arg % 10 === PASSENGER_GENERAL));

        // Check if card is in hand or platform
        $sql = "SELECT * FROM card WHERE card_id = $cardId";
        $cardRow = $this->game->getObjectFromDB($sql);
        if (!$cardRow) {
            throw new UserException(clienttranslate("Card not found"));
        }
        
        if ($cardRow['card_type'] !== 'bus') {
            throw new UserException(clienttranslate("Selected card is not a bus"));
        }
        
        if ($cardRow['card_location'] === 'hand' && (int)$cardRow['card_location_arg'] !== $playerId) {
            throw new UserException(clienttranslate("This bus is not in your hand"));
        }
        
        if ($cardRow['card_location'] !== 'hand' && $cardRow['card_location'] !== 'platform') {
             throw new UserException(clienttranslate("This bus is not available"));
        }

        if ($cardRow['card_location'] === 'platform' && $this->game->isBusStalled($cardId)) {
             throw new UserException(clienttranslate("This bus is stalled and cannot be selected"));
        }

        // If it was in hand, move it to platform
        if ($cardRow['card_location'] === 'hand') {
            $platformCount = (int) $this->game->getUniqueValueFromDB("SELECT COUNT(*) FROM card WHERE card_location = 'platform'");
            $this->game->cards->moveCard($cardId, 'platform', $platformCount + 1);
            $card = $this->game->cards->getCard($cardId);
            
            $this->bga->notify->all("busPlaced", clienttranslate('${player_name} places a bus on the platform'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
                "card_id" => $cardId,
                "bus_data" => $card, // Formatted Card object
            ]);
        } else {
            $this->bga->notify->all("busSelected", clienttranslate('${player_name} selects a bus from the platform'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
                "card_id" => $cardId,
            ]);
        }

        $this->game->setGameStateValue('active_bus_id', $cardId);
        $this->game->setGameStateValue('boarding_happened', 0); // Reset for this turn

        return WaitingRoom::class;
    }

    function zombie(int $playerId) {
        $args = $this->getArgs();
        if (!empty($args['handBuses'])) {
            return $this->actSelectBus($args['handBuses'][0]->id);
        } elseif (!empty($args['platformBuses'])) {
            return $this->actSelectBus($args['platformBuses'][0]->id);
        }
        return NextPlayer::class;
    }
}
