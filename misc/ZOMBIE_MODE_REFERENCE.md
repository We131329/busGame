# BGA Zombie Mode Reference

When a player leaves a game for any reason (being expelled out of time or quitting the game), he becomes a "zombie player". In this case, the results of the game won't count for statistics, but we must let the other players finish the game anyway.

That's why zombie mode exists: allow the other player to finish the game, against the Zombie (a bot) that replaces the leaver player.

## The Different Zombie Levels

### Level 0: The Passing Zombie
- Just pass to the next step without doing any action.
- *Example*: In 7 Wonders, take a random card and discard it.
- *Example*: In Catan, roll the dice and pass.

### Level 1: The Random Zombie
- Code a random possible action.
- *Example*: In 7 Wonders, take a random card and play it.
- *Example*: In Catan, roll the dice and take a random possible action.

### Level 2: The Greedy Zombie
- Take the action that will bring the most value given the visible information.
- Doesn't plan for future moves, nor remember previously known information.
- Does not try to take a lower value in order to block the opponent.
- *Example*: In 7 Wonders, take a card that will bring the most points. If tied, the one that costs less.
- *Example*: In Catan, roll the dice and play the action that brings the most points.

### Level 3: The Smart Zombie
- Can plan and/or remember previously revealed information.
- *Example*: In 7 Wonders, remember which cards could be back next turn.
- *Example*: In Catan, trade only if it's in the benefit of the zombie player.

## Development Principles
- **Aim for Level 1 or 2** for standard implementations.
- **Level 0 is fine for solo-only games**.
- Do not refer to the rules (not planned by rules).
- Imagine playing with friends where one has to leave: finish the game without killing the spirit.
- **Goal is NOT AI**: It's just to keep the game moving.
- **Do not end the game early**: The zombie allows the game to continue.

## Selection Rule
Indicate the level in the Game Metadata Manager.
- If levels vary, indicate the predominant one.
- In case of a tie, indicate the lowest one.
