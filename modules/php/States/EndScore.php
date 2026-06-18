<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\States;

use Bga\GameFramework\StateType;
use Bga\Games\BUUUUUUUUS\Game;

class EndScore extends \Bga\GameFramework\States\GameState
{

    function __construct(
        protected Game $game,
    ) {
        parent::__construct($game,
            id: 95,
            type: StateType::GAME,
        );
    }

    function onEnteringState(int $activePlayerId) {
        $players = $this->game->loadPlayersBasicInfos();
        $round = (int)$this->game->getGameStateValue('round');
        
        $scores = [];
        $totalScores = [];

        foreach ($players as $pId => $player) {
            $pId = (int)$pId;
            $scoreDetails = [
                'passengers' => 0,
                'lovers_bonus' => 0,
                'unhappies' => 0,
                'hand_passengers' => 0,
                'hand_buses' => 0,
                'night_buses' => 0,
                'total' => 0,
            ];

            // 1. Passengers (+1 each)
            $passengers = $this->game->cards->getCardsInLocation('passengers', $pId);
            $scoreDetails['passengers'] = count($passengers);

            // 2. Lovers Bonus (+4 per pair of same color)
            $loversByColor = [];
            foreach ($passengers as $p) {
                if ($p->type_arg % 10 == PASSENGER_LOVERS) {
                    $color = (int) floor($p->type_arg / 10);
                    $loversByColor[$color] = ($loversByColor[$color] ?? 0) + 1;
                }
            }
            foreach ($loversByColor as $color => $count) {
                $pairs = (int) floor($count / 2);
                $scoreDetails['lovers_bonus'] += ($pairs * 4);
                $this->game->bga->playerStats->inc('happy_lovers', $pairs, $pId);
            }

            // 3. Unhappies (-1 each)
            $unhappies = $this->game->cards->getCardsInLocation('unhappies', $pId);
            $scoreDetails['unhappies'] = -count($unhappies);
            $this->game->bga->playerStats->inc('unhappy_passengers', count($unhappies), $pId);

            $unhappyLoversCount = count(array_filter($unhappies, fn($p) => $p->type_arg % 10 == PASSENGER_LOVERS));
            $this->game->bga->playerStats->inc('unhappy_lovers', $unhappyLoversCount, $pId);

            // 4. Travelers in hand (-1 each)
            $hand = $this->game->cards->getCardsInLocation('hand', $pId);
            $handPassengers = array_filter($hand, fn($c) => $c->type === 'passenger');
            $scoreDetails['hand_passengers'] = -count($handPassengers);
            
            $handStat = ($round === 1) ? 'hand_passengers_round1' : 'hand_passengers_round2';
            $this->game->bga->playerStats->set($handStat, count($handPassengers), $pId);

            // 5. Buses in hand (- capacity each)
            $handBuses = array_filter($hand, fn($c) => $c->type === 'bus');
            foreach ($handBuses as $bus) {
                $capacity = (int) $bus->type_arg % 10;
                $scoreDetails['hand_buses'] -= $capacity;
            }

            // 6. Night Buses (+1 for each passenger on platform buses if hand was empty)
            if (empty($hand)) {
                $platformPassengers = $this->game->cards->getCardsInLocation('in_bus');
                $scoreDetails['night_buses'] = count($platformPassengers);
            }

            $scoreDetails['total'] = array_sum($scoreDetails);
            $scores[$pId] = $scoreDetails;
            
            // Round Points Stat
            $roundStat = ($round === 1) ? 'round1_points' : 'round2_points';
            $this->game->bga->playerStats->set($roundStat, $scoreDetails['total'], $pId);

            // Update DB
            $this->game->bga->playerScore->inc($scoreDetails['total'], $pId);

            // Tiebreaker: Most Passengers (only at the end of Round 2)
            if ($round === 2) {
                $totalPassengers = count($passengers);
                $this->game->bga->playerScoreAux->set($totalPassengers, $pId);
            }
        }

        // Get updated scores from database
        $newScores = [];
        foreach ($players as $pId => $player) {
            $newScores[(int)$pId] = $this->game->bga->playerScore->get((int)$pId);
        }

        $this->game->bga->notify->all("scoring", clienttranslate('End of Round ${round} scoring!'), [
            "round" => $round,
            "scores" => $scores,
            "new_scores" => $newScores,
        ]);

        foreach ($players as $pId => $player) {
            $pId = (int)$pId;
            $this->game->bga->notify->all("message", clienttranslate('${player_name} scores ${points} points this round.'), [
                "player_id" => $pId,
                "player_name" => $this->game->getPlayerNameById($pId),
                "points" => $scores[$pId]['total'],
            ]);
        }

        if ($round < 2) {
            // Determine starting player for round 2 (highest player_score)
            $sql = "SELECT player_id FROM player ORDER BY player_score DESC, player_id ASC LIMIT 1";
            $startingPlayerId = (int)$this->game->getUniqueValueFromDB($sql);
            $this->game->gamestate->changeActivePlayer($startingPlayerId);

            $this->prepareNextRound();
            return ActiveBus::class;
        }

        $this->gamestate->jumpToState(99);
        return null;
    }

    private function prepareNextRound()
    {
        $this->game->setGameStateValue('round', 2);
        
        // 1. Move everything to deck
        Game::DbQuery("UPDATE card SET card_location='deck', card_location_arg=0");
        
        // 2. Reset unhappy points for UI
        Game::DbQuery("UPDATE player SET player_unhappy_points = 0");

        // 3. Shuffle
        $this->game->cards->shuffle('bus');
        $this->game->cards->shuffle('passenger');

        // 4. Deal to players
        $players = array_keys($this->game->loadPlayersBasicInfos());
        foreach ($players as $pId) {
            $pId = (int)$pId;
            // 2 Buses
            for ($i = 0; $i < 2; $i++) {
                $this->game->cards->pickCard('bus', 'hand', $pId);
            }
            // 7 Passengers
            for ($i = 0; $i < 7; $i++) {
                $this->game->cards->pickCard('passenger', 'hand', $pId);
            }
        }

        // 5. Fill Garage (4 buses)
        for ($i = 1; $i <= 4; $i++) {
            $this->game->cards->pickCard('bus', 'garage', $i);
        }

        // 6. Fill Waiting Room (8 passengers)
        for ($col = 1; $col <= 2; $col++) {
            for ($row = 1; $row <= 4; $row++) {
                $this->game->cards->pickCard('passenger', 'waiting_room', $col * 10 + $row);
            }
        }

        $this->game->applyBabyPriority();
        
        // Reset turn trigger
        $this->game->setGameStateValue('round_end_trigger', 0);
        $this->game->setGameStateValue('remaining_turns', 0);

        // 7. Notify all players about the public setup
        $this->game->bga->notify->all("newRound", clienttranslate('Starting Round 2!'), [
            'garage' => array_values($this->game->cards->getCardsInLocation('garage')),
            'waiting_room' => array_values($this->game->cards->getCardsInLocation('waiting_room')),
        ]);

        // 8. Notify each player about their private hand
        foreach ($players as $pId) {
            $pId = (int)$pId;
            $this->game->bga->notify->player($pId, "newHand", '', [
                'hand' => array_values($this->game->cards->getCardsInLocation('hand', $pId)),
            ]);
        }
    }
}
