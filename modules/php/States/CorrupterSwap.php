<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class CorrupterSwap extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 16,
            type: StateType::ACTIVE_PLAYER,
            transitions: [
                'next' => TravelersHand::class,
                'penalty' => Penalty::class,
                'depart' => RefillHand::class,
                'loop' => CorrupterSwap::class,
                'drunkard' => DrunkardChoice::class,
            ]
        );
    }

    public function getArgs(): array
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $hand = $this->game->cards->getCardsInLocation('hand', $playerId);
        
        $waitingRoom = $this->game->cards->getCardsInLocation('waiting_room');
        $platformBuses = $this->game->cards->getCardsInLocation('platform');
        
        $swapsLeft = (int) $this->game->getGameStateValue('corrupter_swaps_pending');
        $corrupterId = (int) $this->game->getGameStateValue('last_corrupter_id');

        return [
            "hand" => array_values($hand),
            "waitingRoom" => array_values($waitingRoom),
            "platformBuses" => array_values($platformBuses),
            "swapsLeft" => $swapsLeft,
            "corrupterId" => $corrupterId,
        ];
    }    

    #[PossibleAction]
    public function actSwap(int $handCardId, int $targetCardId)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        
        // 1. Verify hand card is a traveler in player's hand
        $handCard = $this->game->cards->getCard($handCardId);
        if (!$handCard || $handCard->location !== 'hand' || (int)$handCard->location_arg !== $playerId || $handCard->type !== 'passenger') {
            throw new UserException($this->game->_("You must select a passenger from your hand"));
        }
        
        // 2. Verify target card
        $targetCard = $this->game->cards->getCard($targetCardId);
        if (!$targetCard || $targetCard->type !== 'passenger') {
            throw new UserException($this->game->_("Target must be a passenger"));
        }
        
        $targetBusId = null;
        if ($targetCard->location === 'waiting_room') {
            // No color restriction for Waiting Room
        } elseif ($targetCard->location === 'in_bus') {
            // Color matching required for Bus passengers
            $targetBusId = (int) $targetCard->location_arg;
            $bus = $this->game->cards->getCard($targetBusId);
            $busColor = (int) floor($bus->type_arg / 10);
            
            $handCardColor = (int) floor($handCard->type_arg / 10);
            $handAbility = $handCard->type_arg % 10;
            
            if ($handCardColor !== $busColor && $handAbility !== PASSENGER_BACKPACKER) {
                 throw new UserException($this->game->_("The passenger from your hand must match the color of the bus"));
            }
        } else {
            throw new UserException($this->game->_("Invalid target for swap. Must be in Waiting Room or in a Bus."));
        }
        
        // 3. Perform Swap
        $targetLoc = $targetCard->location;
        $targetLocArg = (int)$targetCard->location_arg;
        
        $this->game->cards->moveCard($handCardId, $targetLoc, $targetLocArg);
        $this->game->cards->moveCard($targetCardId, 'hand', $playerId);
        
        $this->game->bga->notify->all("corrupterSwap", clienttranslate('${player_name} uses a Corrupter to swap cards!'), [
            "player_id" => $playerId,
            "player_name" => $this->game->getPlayerNameById($playerId),
            "hand_card_id" => $handCardId,
            "hand_card_data" => $this->game->cards->getCard($handCardId),
            "target_card_id" => $targetCardId,
            "target_card_data" => $this->game->cards->getCard($targetCardId),
        ]);

        // 4. Check for departure if we swapped into a bus
        $departState = null;
        if ($targetBusId !== null) {
            $departState = $this->game->checkBusDeparture($targetBusId);
        }
        
        // 5. Update swaps left
        $swapsLeft = (int) $this->game->getGameStateValue('corrupter_swaps_pending') - 1;
        $this->game->setGameStateValue('corrupter_swaps_pending', $swapsLeft);
        
        if ($swapsLeft > 0) {
            if ($departState !== null) {
                // If a bus departed, we might need to store its effect
                $this->game->setGameStateValue('bus_departed_during_corrupter', $departState === 'refill' ? 1 : ($departState === 'drunkard' ? 2 : 0));
            }
            return CorrupterSwap::class;
        } else {
            if ($departState !== null) {
                $this->game->setGameStateValue('bus_departed_during_corrupter', $departState === 'refill' ? 1 : ($departState === 'drunkard' ? 2 : 0));
            }
            return $this->getNextState();
        }
    }

    #[PossibleAction]
    public function actSkip()
    {
        $this->game->setGameStateValue('corrupter_swaps_pending', 0);
        return $this->getNextState();
    }

    protected function getNextState()
    {
        $departed = (int) $this->game->getGameStateValue('bus_departed_during_corrupter');
        if ($departed === 2) {
            return DrunkardChoice::class;
        }
        if ($departed === 1) {
            return RefillHand::class;
        }
        
        $origin = (int) $this->game->getGameStateValue('corrupter_origin_state');
        if ($origin === 1) { // WaitingRoom
            return WaitingRoom::class;
        } else { // 2 = TravelersHand
            return TravelersHand::class;
        }
    }

    function zombie(int $playerId) {
        return $this->actSkip();
    }
}
