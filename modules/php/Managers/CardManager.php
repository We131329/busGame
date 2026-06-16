<?php
namespace Bga\Games\BUUUUUUUUS\Managers;

use Bga\Games\BUUUUUUUUS\Objects\Card;

class CardManager {
    protected $game;

    public function __construct($game) {
        $this->game = $game;
    }

    public function createCards(array $cards) {
        $values = [];
        foreach ($cards as $card) {
            $values[] = "('{$card['type']}', {$card['type_arg']}, '{$card['location']}', {$card['location_arg']})";
        }
        $sql = "INSERT INTO card (card_type, card_type_arg, card_location, card_location_arg) VALUES " . implode(',', $values);
        $this->game->DbQuery($sql);
    }

    public function getCardsInLocation(string $location, ?int $location_arg = null): array {
        $sql = "SELECT * FROM card WHERE card_location = '$location'";
        if ($location_arg !== null) {
            $sql .= " AND card_location_arg = $location_arg";
        }
        $sql .= " ORDER BY card_id ASC";
        $rows = $this->game->getCollectionFromDb($sql);
        return array_map(fn($row) => new Card($row), $rows);
    }

    public function moveCard(int $cardId, string $location, int $location_arg = 0) {
        $sql = "UPDATE card SET card_location = '$location', card_location_arg = $location_arg WHERE card_id = $cardId";
        $this->game->DbQuery($sql);
    }

    public function shuffle(string $type) {
        $cards = $this->game->getCollectionFromDb("SELECT card_id FROM card WHERE card_type = '$type' AND card_location = 'deck'");
        $ids = array_keys($cards);
        shuffle($ids);
        foreach ($ids as $index => $id) {
            $this->game->DbQuery("UPDATE card SET card_location_arg = $index WHERE card_id = $id");
        }
    }

    public function getCard(int $cardId): ?Card {
        $sql = "SELECT * FROM card WHERE card_id = $cardId";
        $row = $this->game->getObjectFromDB($sql);
        if (!$row) return null;
        return new Card($row);
    }

    public function pickCard(string $type, string $toLocation, int $toLocationArg = 0): ?Card {
        $sql = "SELECT * FROM card WHERE card_type = '$type' AND card_location = 'deck' ORDER BY card_location_arg DESC LIMIT 1";
        $row = $this->game->getObjectFromDB($sql);
        if (!$row) return null;
        
        $card = new Card($row);
        $this->moveCard($card->id, $toLocation, $toLocationArg);
        $card->location = $toLocation;
        $card->location_arg = $toLocationArg;
        return $card;
    }
}
