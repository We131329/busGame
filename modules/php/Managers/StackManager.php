<?php

declare(strict_types=1);

namespace Bga\Games\BUUUUUUUUS\Managers;

/**
 * StackManager: Handles the resolution stack (Atoms) for multi-step sequences.
 */
class StackManager
{
    /**
     * Push a new state/atom to the top of the stack.
     */
    public static function push(string $stateName, ?int $playerId = null, array $args = []): void
    {
        $argsJson = json_encode($args);
        \Bga\GameFramework\Table::DbQuery("INSERT INTO `stack` (`state_name`, `player_id`, `args`) VALUES ('$stateName', " . ($playerId ?? 'NULL') . ", '$argsJson')");
    }

    /**
     * Get the top element of the stack without removing it.
     */
    public static function peek(): ?array
    {
        $res = \Bga\GameFramework\Table::getObjectFromDb("SELECT * FROM `stack` ORDER BY `stack_id` DESC LIMIT 1");
        if ($res && isset($res['args'])) {
            $res['args'] = json_decode($res['args'], true);
        }
        return $res;
    }

    /**
     * Remove the top element from the stack.
     */
    public static function pop(): void
    {
        $top = self::peek();
        if ($top) {
            $id = $top['stack_id'];
            \Bga\GameFramework\Table::DbQuery("DELETE FROM `stack` WHERE `stack_id` = $id");
        }
    }

    /**
     * Clear the entire stack.
     */
    public static function clear(): void
    {
        \Bga\GameFramework\Table::DbQuery("DELETE FROM `stack` WHERE 1");
    }

    /**
     * Check if the stack is empty.
     */
    public static function isEmpty(): bool
    {
        return (int)\Bga\GameFramework\Table::getUniqueValueFromDb("SELECT COUNT(*) FROM `stack`") === 0;
    }
}
