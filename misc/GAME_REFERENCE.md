# Bus Game Reference

## Components
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

## Setup
- Shuffle decks.
- Deal 2 Buses + 7 Passengers to each player.
- **Garage**: 1 column of 4 Bus cards.
- **Waiting Room**: 2 columns of 4 Passenger cards (total 8).
- **Platform**: Central area for active/waiting buses.

## Game Structure
- 2 Rounds. Each round has a Play Phase and Scoring Phase.

## Turn Structure (6 Mini-phases)
1. **Active Bus**:
   - Play Bus from hand to Platform.
   - OR select a Bus on Platform if at least 1 passenger in Waiting Room row 4 can board it.
2. **The Waiting Room**:
   - MUST board all eligible passengers (matching color) from Waiting Room.
   - Only bottom-most cards in the 2 columns are eligible.
   - If bus fills up, it **departs**. The player skips directly to **Complete your Hand** and then **Next Player**.
3. **The Travelers Hand**:
   - Play matching color Passenger cards from hand.
   - If bus fills up, it **departs**. The player skips directly to **Complete your Hand** and then **Next Player**.
4. **Penalty**:
   - If player placed a bus from hand but NO passengers (from WR or hand) boarded, discard 1 Passenger from hand to "Unhappies".
5. **Complete your Hand**:
   - Refill hand to 7 Passengers (from WR, any position) and 2 Buses (from Garage).
6. **Next Player**:
   - Refill Garage to 4.
   - Refill WR: slide remaining cards down, refill from top (Col 1 then Col 2).

## End of Round
- **Case 1**: Garage empty + active player can't play bus or board (reveals hand). Others get 1 extra turn.
- **Case 2**: A player runs out of cards in hand. Immediate end.

## Scoring
- +1 point per card in "Passengers" zone.
- -1 point per card in "Unhappies" zone.
- -1 point per Passenger card in hand.
- -Capacity points per Bus card in hand.
- +4 points per pair of same-color "The Lovers".
- If hand empty: +1 point per Passenger card on the "Platform".

## Special Passenger Skills
- **Surfer**: Needs 2+ empty spaces.
- **Lovers**: If bus hits max capacity with them, it "stalls" and cannot depart? (Needs clarification).
- **General**: Bus departs immediately even if not full. Sends Lovers in that bus to Unhappies.
- **Star**: If in hand, can call a bus from Garage/other players at start of turn.
- **Drunkard**: On depart, 2 other passengers go to Unhappies (player's choice).
- **Backpacker**: Wild color. Must be taken from WR if possible.
- **Corrupter**: On boarding, swap hand card with any in WR OR matching color card on Platform.
- **Baby**: Moves to Row 4 (bottom) in Waiting Room.
