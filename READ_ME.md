# BUUUUUUUUS - Board Game Arena Implementation

## Project Overview
This project is a digital implementation of the card game **BUUUUUUUUS** for the Board Game Arena platform. It uses the modern BGA framework (ESM, PHP Namespaces, Class-based States).

**Developer**: Erickbond <perickpor@hotmail.com>

---

## Game Reference

### Components
- **Bus Deck (16 cards)**:
  - 4 colors: Blue, Red, Green, Yellow.
  - Per color: 1x Cap 3, 2x Cap 4, 1x Cap 5.
- **Passenger Deck (56 cards)**:
  - 4 colors: Blue, Red, Green, Yellow.
  - Per color:
    - 5x Anonymous (1 cap, no skill).
    - 1x The Surfer (2 cap, can't exceed capacity).
    - 2x The Lovers (1 cap each, travel in pairs, stalls bus if full).
    - 1x The General (1 cap, instant depart, sends Lovers to Unhappies).
    - 1x The Star (1 cap, call bus at start of turn).
    - 1x The Drunkard (1 cap, sends 2 passengers to Unhappies on depart).
    - 1x The Backpacker (1 cap, wild color).
    - 1x The Corrupter (1 cap, swap cards).
    - 1x The Baby (1 cap, priority in Waiting Room).

### Setup
- **Garage**: 1 column of 4 Bus cards.
- **Waiting Room**: 2 columns of 4 Passenger cards (total 8).
- **Platform**: Central area where buses wait to be filled.

### Game Structure
- **2 Rounds**: Each round consists of multiple turns until an end condition is met.
- **Progression**: Tracks based on remaining buses in the supply and extra turns.

### Turn Structure (6 Mini-phases)
1.  **Active Bus**: Play a Bus from hand to Platform OR select one already on Platform.
2.  **The Waiting Room**: **MANDATORY** boarding of eligible passengers from the bottom row.
3.  **The Travelers Hand**: **OPTIONAL** boarding of matching passengers from your hand.
4.  **Penalty**: If you played a bus but NO boarding occurred, discard a passenger to "Unhappies".
5.  **Complete your Hand**: Refill hand to 7 Passengers and 2 Buses.
6.  **Next Player**: Refill Garage and Waiting Room (sliding babies forward).

### Scoring
- **+1 point**: Each passenger successfully transported (departed).
- **-1 point**: Each passenger in "Unhappies" or still in hand.
- **-Capacity**: For each Bus card remaining in hand.
- **+4 points**: For each pair of same-color Lovers successfully transported.
- **Night Bus Bonus**: If hand is empty, +1 point for each passenger on platform buses.

### Special Abilities
- **Surfer**: Needs 2+ spaces.
- **Lovers**: Stall bus if only one is on board when it's full.
- **General**: Forces instant departure; makes Lovers unhappy.
- **Star**: Call buses from others or Garage.
- **Drunkard**: Sends 2 other passengers to Unhappies on departure.
- **Backpacker**: Wild color.
- **Corrupter**: Swap hand cards with WR/Platform.
- **Baby**: Jumps to the front of the Waiting Room queue.

---

## Technical Details

### Architecture
- **Server-side**: PHP 8.2+ using Namespaces. Logic is split into individual State classes in `modules/php/States/`.
- **Client-side**: JavaScript (ESM) using a State Pattern. UI classes in `Game.js` match the PHP states.
- **CSS**: Namespaced with `buu_` prefix to prevent collisions.
- **Database**: Custom `card` table handles all game elements (buses and passengers).
