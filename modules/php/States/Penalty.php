<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class Penalty extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 14,
            type: StateType::ACTIVE_PLAYER,
        );
    }

    public function onEnteringState(int $activePlayerId)
    {
        $boardingHappened = $this->game->getGameStateValue('boarding_happened');
        if ($boardingHappened) {
            $this->bga->notify->all("message", clienttranslate('${player_name} avoids a penalty because boarding happened this turn.'), [
                'player_name' => $this->game->getPlayerNameById($activePlayerId)
            ]);
            return RefillHand::class;
        }
        // If no boarding happened, they MUST take a penalty
    }

    public function getArgs(): array
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $hand = $this->game->cards->getCardsInLocation('hand', $playerId);
        
        return [
            "hand" => array_values($hand),
        ];
    }    

    #[PossibleAction]
    public function actChoosePenalty(int $cardId)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        
        $this->game->cards->moveCard($cardId, 'unhappies', $playerId);

        // Increase unhappy points
        Game::DbQuery("UPDATE player SET player_unhappy_points = player_unhappy_points + 1 WHERE player_id = $playerId");

        $this->bga->notify->all("penaltyTaken", clienttranslate('${player_name} takes a penalty and moves a card to Unhappies'), [
            "player_id" => $playerId,
            "player_name" => $this->game->getPlayerNameById($playerId),
            "card_id" => $cardId,
            "new_unhappy_points" => $this->game->getUniqueValueFromDB("SELECT player_unhappy_points FROM player WHERE player_id = $playerId"),
        ]);

        return RefillHand::class;
    }

    function zombie(int $playerId) {
        $args = $this->getArgs();
        if (!empty($args['hand'])) {
            return $this->actChoosePenalty($args['hand'][0]->id);
        }
        return RefillHand::class;
    }
}
