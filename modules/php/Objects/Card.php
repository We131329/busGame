<?php
namespace Bga\Games\BUUUUUUUUS\Objects;

class Card {
    public int $id;
    public string $type; // 'bus' or 'passenger'
    public int $type_arg; // color * 10 + (ability or capacity)
    public string $location;
    public int $location_arg;
    public ?int $owner;

    public function __construct(array $dbRow) {
        $this->id = (int) $dbRow['card_id'];
        $this->type = $dbRow['card_type'];
        $this->type_arg = (int) $dbRow['card_type_arg'];
        $this->location = $dbRow['card_location'];
        $this->location_arg = (int) $dbRow['card_location_arg'];
        $this->owner = isset($dbRow['card_owner']) ? (int) $dbRow['card_owner'] : null;
    }

    public function getColor(): int {
        return (int) floor($this->type_arg / 10);
    }

    public function getArg(): int {
        return $this->type_arg % 10;
    }
}
