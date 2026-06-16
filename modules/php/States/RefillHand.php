<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class RefillHand extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 15,
            type: StateType::ACTIVE_PLAYER,
            updateGameProgression: true,
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        $args = $this->getArgs();
        $playerId = (int)$activePlayerId;

        if ($args['missingBuses'] === 0 && $args['missingPassengers'] === 0) {
            $msg = "";
            if ($args['missingBusesRaw'] === 0 && $args['missingPassengersRaw'] === 0) {
                $msg = clienttranslate('${player_name} already has the maximum amount of cards in hand.');
            } else {
                if ($args['missingBusesRaw'] > 0 && $args['missingPassengersRaw'] > 0) {
                    $msg = clienttranslate('No more buses or passengers available to refill ${player_name}\'s hand.');
                } elseif ($args['missingBusesRaw'] > 0) {
                    $msg = clienttranslate('No more buses available to refill ${player_name}\'s hand.');
                } else {
                    $msg = clienttranslate('No more passengers available to refill ${player_name}\'s hand.');
                }
            }

            $this->game->bga->notify->all("message", $msg, [
                'player_name' => $this->game->getPlayerNameById($playerId)
            ]);
            return NextPlayer::class;
        }
    }

    public function getArgs(): array
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $hand = $this->game->cards->getCardsInLocation('hand', $playerId);

        $busCountInHand = count(array_filter($hand, fn($c) => $c->type === 'bus'));
        $passengerCountInHand = count(array_filter($hand, fn($c) => $c->type === 'passenger'));

        $garage = $this->game->cards->getCardsInLocation('garage');
        $waitingRoom = $this->game->cards->getCardsInLocation('waiting_room');

        $missingBusesRaw = max(0, 2 - $busCountInHand);
        $missingPassengersRaw = max(0, 7 - $passengerCountInHand);

        // Cap by availability
        $missingBuses = min($missingBusesRaw, count($garage));
        $missingPassengers = min($missingPassengersRaw, count($waitingRoom));

        return [
            "garage" => array_values($garage),
            "waitingRoom" => array_values($waitingRoom),
            "missingBuses" => $missingBuses,
            "missingPassengers" => $missingPassengers,
            "missingBusesRaw" => $missingBusesRaw,
            "missingPassengersRaw" => $missingPassengersRaw,
        ];
    }    


    #[PossibleAction]
    public function actRefill(string $cardIds)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $ids = array_filter(array_map('intval', explode(',', $cardIds)));
        
        // Logic for refilling (e.g. up to a certain hand size)
        foreach ($ids as $cardId) {
            $this->game->cards->moveCard($cardId, 'hand', $playerId);
        }
        
        if (!empty($ids)) {
            $this->bga->notify->all("handRefilled", clienttranslate('${player_name} refills hand with ${count} cards'), [
                "player_id" => $playerId,
                "player_name" => $this->game->getPlayerNameById($playerId),
                "count" => count($ids),
                "card_ids" => $ids,
            ]);
        }

        return NextPlayer::class;
    }

    function zombie(int $playerId) {
        $args = $this->getArgs();
        $selected = [];
        
        // Pick first available buses from garage
        $busesToPick = min($args['missingBuses'], count($args['garage']));
        for ($i = 0; $i < $busesToPick; $i++) {
            $selected[] = $args['garage'][$i]->id;
        }
        
        // Pick first available passengers from waiting room
        $passengersToPick = min($args['missingPassengers'], count($args['waitingRoom']));
        for ($i = 0; $i < $passengersToPick; $i++) {
            $selected[] = $args['waitingRoom'][$i]->id;
        }
        
        return $this->actRefill(implode(',', $selected));
    }
}
