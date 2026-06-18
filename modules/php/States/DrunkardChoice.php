<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\GameFramework\States\GameState;
use Bga\GameFramework\States\PossibleAction;
use Bga\GameFramework\UserException;
use Bga\Games\BUUUUUUUUS\Game;

class DrunkardChoice extends GameState
{
    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 17,
            type: StateType::ACTIVE_PLAYER,
            transitions: [
                'next' => RefillHand::class,
            ]
        );
    }

    public function getArgs(): array
    {
        $busId = (int) $this->game->getGameStateValue('drunkard_bus_id');
        $passengers = $this->game->cards->getCardsInLocation('in_bus', $busId);

        return [
            "passengers" => array_values($passengers),
        ];
    }    

    #[PossibleAction]
    public function actSelectVictims(string $victimIds)
    {
        $playerId = (int) $this->game->getActivePlayerId();
        $busId = (int) $this->game->getGameStateValue('drunkard_bus_id');
        
        $ids = array_filter(array_map('intval', explode(',', $victimIds)));
        
        $passengers = $this->game->cards->getCardsInLocation('in_bus', $busId);
        
        // Max 2 victims
        if (count($ids) > 2) {
             throw new UserException(clienttranslate("Select at most 2 victims"));
        }
        
        // If there are others available, must select up to 2
        if (count($ids) < min(2, count($passengers))) {
             throw new UserException(clienttranslate("You must select more victims"));
        }

        // Verify victims are on the bus
        foreach ($ids as $vId) {
            $found = false;
            foreach ($passengers as $p) {
                if ($p->id === $vId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new UserException(clienttranslate("One of the selected victims is not on the bus"));
            }
        }

        // Move victims to unhappies
        foreach ($ids as $vId) {
            $this->game->cards->moveCard($vId, 'unhappies', $playerId);
        }
        
        // Move others to passengers
        $remaining = $this->game->cards->getCardsInLocation('in_bus', $busId);
        foreach ($remaining as $r) {
            $this->game->cards->moveCard($r->id, 'passengers', $playerId);
            $this->game->bga->playerStats->inc('passengers_departed', 1, $playerId);
        }
        
        // Move bus to departed_buses
        $this->game->cards->moveCard($busId, 'departed_buses', $playerId);
        
        $this->game->bga->notify->all("drunkardVictimsSelected", clienttranslate('${player_name} chooses victims for the Drunkard!'), [
            "player_id" => $playerId,
            "player_name" => $this->game->getPlayerNameById($playerId),
            "victim_ids" => $ids,
            "bus_id" => $busId,
        ]);
        
        return RefillHand::class;
    }

    function zombie(int $playerId) {
        $args = $this->getArgs();
        $ids = array_map(fn($p) => $p->id, array_slice($args['passengers'], 0, 2));
        return $this->actSelectVictims(implode(',', $ids));
    }
}
