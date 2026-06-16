# Bus Game - Development Work Plan

## Phase 1: Foundation & Setup
- [x] Define database schema (`dbmodel.sql`) for cards (locations: hand, deck, garage, waiting_room, platform, passengers, unhappies).
- [x] Implement `setupNewGame` to initialize decks and deal cards.
- [x] Create `material.inc.php` with card types, colors, and special abilities.
- [x] Implement `getAllDatas` to sync state with UI.
- [x] Basic UI Layout: Garage, Waiting Room (grid), Platform, Player Boards (Passengers/Unhappies).
- [x] Structural alignment with Moluku (StackManager, Namespaces).

## Phase 2: Core Gameplay (The Turn)
- [x] State: `stActiveBus`. Selection from hand or platform.
- [x] State: `stWaitingRoom`. Automated boarding logic (Looping through bottom-most cards).
- [x] State: `stTravelersHand`. Interactive phase for player to play cards.
- [x] State: `stPenalty`. Check if boarding happened; if not, force discard.
- [x] State: `stRefillHand`. Interactive phase to pick cards from WR/Garage.
- [x] State: `stNextPlayer`. Cleanup WR (slide down) and Garage.

## Phase 3: Special Passengers & Departures
- [x] Logic for "Depart": Moving bus + passengers to "Passengers" zone.
- [x] Implement Special Skills:
    - [x] **The General**: Instant depart logic.
    - [x] **The Drunkard**: Selection of victims.
    - [x] **The Corrupter**: Swap logic (Waiting Room vs Platform).
    - [x] **The Star**: Start-of-turn activation.
    - [x] **The Baby**: Logic to reorder Waiting Room.
    - [x] **The Surfer/Lovers**: Capacity constraints and Stall logic.
    - [x] **The Backpacker**: Wild color logic.

## Phase 4: Round End & Scoring
- [x] Check end-of-round conditions (Empty garage/No actions OR Empty hand).
- [x] Scoring phase: Iterate through all zones and calculate points.
- [x] Transition to Round 2 (Reset decks/setup).
- [x] Final scoring and Game End.

## Phase 5: Polish & UX
- [ ] Animations for boarding and departing.
- [ ] Tooltips for card abilities.
- [ ] Visual indicators for "Unhappy" passengers.
- [ ] BGA Notifications for all actions.
