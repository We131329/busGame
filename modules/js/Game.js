/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * BUUUUUUUUS implementation : © Erickbond <perickpor@hotmail.com>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

class ActiveBus {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedBusId = null;
        this.selectedStarId = null;
        this.starAbilityActive = false;
        this.args = null;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.args = args;
        this.updateTitle(isCurrentPlayerActive);
      
        if (isCurrentPlayerActive) {
            this.updateButtons();
            this.refreshSelectability();
        }

        // Visual feedback for stalled buses
        document.querySelectorAll('#platform .buu_card.buu_bus').forEach(el => {
            const busId = el.dataset.id;
            if (this.game.isBusStalled(busId)) {
                el.classList.add('buu_stalled-visual');
            } else {
                el.classList.remove('buu_stalled-visual');
            }
        });
    }

    updateTitle(isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${actplayer} must select a bus'));
            return;
        }

        if (this.starAbilityActive) {
            if (!this.selectedStarId) {
                this.bga.statusBar.setTitle(_('${you} must select a Star from your hand'));
            } else {
                this.bga.statusBar.setTitle(_('${you} must select a matching bus from Garage, Hand or Departed'));
            }
        } else {
            this.bga.statusBar.setTitle(_('${you} must select a bus from your hand or the platform'));
        }
    }

    refreshSelectability() {
        this.game.setSelectable('bus', false);
        this.game.setSelectable('passenger', false);

        if (this.starAbilityActive) {
            if (!this.selectedStarId) {
                // Selectable stars in hand
                this.args.handStars.forEach(s => {
                    const el = document.getElementById(`card-${s.id}`);
                    if (el) el.classList.add('buu_selectable');
                });
            } else {
                // Selectable buses of matching color
                const star = this.args.handStars.find(s => s.id == this.selectedStarId);
                const starColor = Math.floor(star.type_arg / 10);
                
                const allPotentialBuses = [
                    ...(this.args.garageBuses || []),
                    ...(this.args.departedBuses || [])
                ];

                allPotentialBuses.forEach(b => {
                    if (Math.floor(b.type_arg / 10) === starColor) {
                        const el = document.getElementById(`card-${b.id}`);
                        if (el) el.classList.add('buu_selectable');
                    }
                });
            }
        } else {
            this.game.setSelectable('bus', true, 'my-hand-buses');
            
            // Buses on the platform
            const platformBuses = document.querySelectorAll('#platform .buu_card.buu_bus');
            if (platformBuses.length === 0) {
                console.warn("No buses found on platform for selectability check");
            }

            platformBuses.forEach(el => {
                const busId = el.dataset.id;
                const isStalled = this.game.isBusStalled(busId);
                
                if (!isStalled) {
                    el.classList.add('buu_selectable');
                } else {
                    el.classList.add('buu_unselectable-visual');
                }
            });
        }
    }

    onLeavingState() {
        this.game.setSelectable('bus', false);
        this.game.setSelectable('passenger', false);
        this.selectedBusId = null;
        this.selectedStarId = null;
        this.starAbilityActive = false;
    }

    onCardClick(cardId, type) {
        if (this.starAbilityActive) {
            if (type === 'passenger') {
                const star = this.args.handStars.find(s => s.id == cardId);
                if (star) {
                    this.game.clearCardSelection();
                    this.selectedStarId = cardId;
                    this.game.selectCard(cardId, true);
                    this.refreshSelectability();
                    this.updateButtons();
                    this.updateTitle(true);
                }
            } else if (type === 'bus') {
                this.selectedBusId = cardId;
                this.game.selectCard(cardId, true);
                this.updateButtons();
            }
            return;
        }

        if (type !== 'bus') return;
        this.game.clearCardSelection();
        this.selectedBusId = cardId;
        this.game.selectCard(cardId, true);
        this.updateButtons();
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();

        if (this.starAbilityActive) {
            if (this.selectedStarId && this.selectedBusId) {
                this.bga.statusBar.addActionButton(_('Confirm Star'), () => this.onConfirmStar());
            }
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancelStar(), { color: 'gray' });
            return;
        }

        // Star Ability Initial Buttons
        if (this.args && this.args.handStars && this.args.handStars.length > 0 && !this.selectedBusId) {
            this.bga.statusBar.addActionButton(_('Activate Star Ability'), () => this.onActivateStar());
        }

        if (this.selectedBusId) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm());
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        } else if (this.args && this.args.canTriggerEnd) {
            const label = (this.args.roundEndTriggeredBy !== 0) ? _('Skip Turn') : _('End Round');
            this.bga.statusBar.addActionButton(label, () => this.onTriggerEnd(), { color: 'red' });
        }
    }

    onTriggerEnd() {
        this.bga.actions.performAction("actTriggerEnd");
    }

    onActivateStar() {
        this.starAbilityActive = true;
        this.refreshSelectability();
        this.updateButtons();
        this.updateTitle(true);
    }

    onCancelStar() {
        this.starAbilityActive = false;
        this.selectedStarId = null;
        this.selectedBusId = null;
        this.game.clearCardSelection();
        this.refreshSelectability();
        this.updateButtons();
        this.updateTitle(true);
    }

    onConfirmStar() {
        this.bga.actions.performAction("actUseStar", { starId: this.selectedStarId, busId: this.selectedBusId });
    }

    onConfirm() {
        this.bga.actions.performAction("actSelectBus", { cardId: this.selectedBusId });
    }

    onCancel() {
        this.game.selectCard(this.selectedBusId, false);
        this.selectedBusId = null;
        this.updateButtons();
    }
}

class WaitingRoom {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedIds = [];
        this.args = null;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.args = args;
        this.updateTitle(isCurrentPlayerActive);
      
        if (isCurrentPlayerActive) {
            this.refreshSelectability();
            this.updateButtons();
        }
    }

    updateTitle(isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${actplayer} is boarding passengers'));
            return;
        }

        if (this.hasMandatorySelections()) {
            this.bga.statusBar.setTitle(_('${you} must select all eligible passengers from the Waiting Room'));
        } else if (this.selectedIds.length > 0) {
            this.bga.statusBar.setTitle(_('${you} have selected all mandatory passengers. You can confirm or add more if possible.'));
        } else {
            this.bga.statusBar.setTitle(_('${you} must select passengers from the Waiting Room to board'));
        }
    }

    hasMandatorySelections() {
        const busColor = Math.floor(parseInt(this.args.bus.card_type_arg) / 10);
        const busCapacity = parseInt(this.args.bus.card_type_arg) % 10;
        
        // Calculate current occupancy including selections
        let currentOccupancy = this.game.getBusOccupancy(this.args.bus.card_id);
        this.selectedIds.forEach(id => {
             const typeArg = this.game.getCardTypeArg(id);
             const ability = typeArg % 10;
             currentOccupancy += this.game.gamedatas.passenger_types[ability].capacity;
        });

        if (currentOccupancy >= busCapacity) return false;

        // Check columns for "front" cards that match color and fit
        for (let col = 1; col <= 2; col++) {
            // Find front card NOT in selectedIds
            let frontCard = null;
            for (let row = 4; row >= 1; row--) {
                const pos = col * 10 + row;
                const card = this.args.waitingRoom.find(c => c.location_arg == pos);
                if (!card) continue;
                if (this.selectedIds.includes(card.id)) continue;
                
                frontCard = card;
                break; // Found the front card that isn't selected
            }

            if (frontCard) {
                const cardColor = Math.floor(parseInt(frontCard.type_arg) / 10);
                const ability = parseInt(frontCard.type_arg) % 10;
                const capacity = this.game.gamedatas.passenger_types[ability].capacity;

                if ((cardColor === busColor || ability === 6) && (currentOccupancy + capacity <= busCapacity)) {
                    return true;
                }
            }
        }
        return false;
    }

    refreshSelectability() {
        const busColor = Math.floor(parseInt(this.args.bus.card_type_arg) / 10);
        
        // Reset all first
        document.querySelectorAll('#waiting-room .buu_card').forEach(el => {
            el.classList.add('buu_unselectable-visual');
            el.classList.remove('buu_selectable');
        });

        // For each column, find the chain of selectable cards
        for (let col = 1; col <= 2; col++) {
            for (let row = 4; row >= 1; row--) {
                const pos = col * 10 + row;
                const card = this.args.waitingRoom.find(c => c.location_arg == pos);
                if (!card) continue;

                const cardColor = Math.floor(parseInt(card.type_arg) / 10);
                const ability = parseInt(card.type_arg) % 10;
                const el = document.getElementById(`card-${card.id}`);

                // Color match OR Backpacker (6)
                if (cardColor === busColor || ability === 6) {
                    let hasUnselectedBelow = false;
                    for (let r = row + 1; r <= 4; r++) {
                        const belowPos = col * 10 + r;
                        const cardBelow = this.args.waitingRoom.find(c => c.location_arg == belowPos);
                        if (cardBelow && !this.selectedIds.includes(cardBelow.id)) {
                            hasUnselectedBelow = true;
                            break;
                        }
                    }

                    if (!hasUnselectedBelow) {
                        el.classList.remove('buu_unselectable-visual');
                        el.classList.add('buu_selectable');
                    } else {
                        break; 
                    }
                } else {
                    break;
                }
                
                if (!this.selectedIds.includes(card.id)) {
                    break;
                }
            }
        }
    }

    onLeavingState() {
        this.selectedIds = [];
        document.querySelectorAll('.buu_unselectable-visual').forEach(el => el.classList.remove('buu_unselectable-visual'));
        this.args = null;
    }

    onCardClick(cardId, type, containerId) {
        if (type !== 'passenger' || containerId !== 'waiting-room') return;
        
        const index = this.selectedIds.indexOf(cardId);
        if (index === -1) {
            const el = document.getElementById(`card-${cardId}`);
            if (!el.classList.contains('buu_selectable')) return;

            this.selectedIds.push(cardId);
        } else {
            const card = this.args.waitingRoom.find(c => c.id == cardId);
            const col = Math.floor(card.location_arg / 10);
            const row = card.location_arg % 10;

            const toDeselect = this.selectedIds.filter(id => {
                const c = this.args.waitingRoom.find(curr => curr.id == id);
                return Math.floor(c.location_arg / 10) == col && (c.location_arg % 10) < row;
            });

            [...toDeselect, cardId].forEach(id => {
                const idx = this.selectedIds.indexOf(id);
                if (idx !== -1) {
                    this.selectedIds.splice(idx, 1);
                    this.game.selectCard(id, false);
                }
            });
        }
        this.refreshSelectionOrder();
        this.refreshSelectability();
        this.updateTitle(true);
        this.updateButtons();
    }

    refreshSelectionOrder() {
        // Clear all previous orders
        document.querySelectorAll('.buu_card .buu_card-selection-order').forEach(el => el.textContent = '');
        
        this.selectedIds.forEach((id, idx) => {
            this.game.selectCard(id, true, idx + 1);
        });
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();
        const mandatoryMissing = this.hasMandatorySelections();

        if (this.selectedIds.length > 0) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm(), { 
                color: 'blue',
                opacity: mandatoryMissing ? 0.5 : 1
            });
        }
        
        if (this.selectedIds.length > 0) {
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        }
    }

    onConfirm() {
        if (this.hasMandatorySelections()) {
            this.bga.gameui.showMessage(_('You must select all eligible passengers before confirming'), 'error');
            return;
        }
        this.bga.actions.performAction("actBoardPassengers", { passengerIds: this.selectedIds.join(',') });
    }

    onSkip() {
        // Skip is removed from UI but kept here for safety
        if (this.hasMandatorySelections()) {
            this.bga.gameui.showMessage(_('You cannot skip because there are eligible passengers to board'), 'error');
            return;
        }
        this.bga.actions.performAction("actSkip");
    }

    onCancel() {
        this.selectedIds.forEach(id => this.game.selectCard(id, false));
        this.selectedIds = [];
        this.refreshSelectability();
        this.updateButtons();
    }
}

class TravelersHand {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedIds = [];
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.args = args;
        this.updateTitle(isCurrentPlayerActive);
      
        if (isCurrentPlayerActive) {
            this.refreshSelectability();
            this.updateButtons();
        }
    }

    updateTitle(isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${actplayer} may play passengers from hand'));
            return;
        }

        const busCapacity = parseInt(this.args.bus.card_type_arg) % 10;
        let currentOccupancy = this.game.getBusOccupancy(this.args.bus.card_id);
        this.selectedIds.forEach(id => {
             const typeArg = this.game.getCardTypeArg(id);
             const ability = typeArg % 10;
             currentOccupancy += this.game.gamedatas.passenger_types[ability].capacity;
        });

        const remaining = busCapacity - currentOccupancy;
        if (remaining > 0) {
            this.bga.statusBar.setTitle(_('${you} may play passengers from your hand (remaining capacity: ${n})').replace('${n}', remaining));
        } else {
            this.bga.statusBar.setTitle(_('${you} cannot add more passengers. The bus is full.'));
        }
    }

    refreshSelectability() {
        const busColor = Math.floor(parseInt(this.args.bus.card_type_arg) / 10);
        const busCapacity = parseInt(this.args.bus.card_type_arg) % 10;
        let currentOccupancy = this.game.getBusOccupancy(this.args.bus.card_id);
        this.selectedIds.forEach(id => {
             const typeArg = this.game.getCardTypeArg(id);
             const ability = typeArg % 10;
             currentOccupancy += this.game.gamedatas.passenger_types[ability].capacity;
        });

        document.querySelectorAll('#my-hand-passengers .buu_card').forEach(el => {
            const cardId = el.dataset.id;
            const typeArg = this.game.getCardTypeArg(cardId);
            const cardColor = Math.floor(typeArg / 10);
            const ability = typeArg % 10;
            const cardCapacity = this.game.gamedatas.passenger_types[ability].capacity;

            el.classList.remove('buu_selectable', 'buu_unselectable-visual');
            
            // 1. Color check
            if (cardColor !== busColor && ability !== 6) {
                el.classList.add('buu_unselectable-visual');
            } 
            // 2. Capacity check (only if not already selected)
            else if (!this.selectedIds.includes(cardId) && (currentOccupancy + cardCapacity > busCapacity)) {
                el.classList.add('buu_unselectable-visual');
            }
            else {
                el.classList.add('buu_selectable');
            }
        });
    }

    onLeavingState() {
        this.game.setSelectable('passenger', false);
        this.selectedIds = [];
        document.querySelectorAll('.buu_unselectable-visual').forEach(el => el.classList.remove('buu_unselectable-visual'));
        this.args = null;
    }

    onCardClick(cardId, type, containerId) {
        if (type !== 'passenger' || containerId !== 'my-hand-passengers') return;
        
        const el = document.getElementById(`card-${cardId}`);
        if (el.classList.contains('buu_unselectable-visual') && !this.selectedIds.includes(cardId)) return;

        const index = this.selectedIds.indexOf(cardId);
        if (index === -1) {
            this.selectedIds.push(cardId);
        } else {
            this.selectedIds.splice(index, 1);
            this.game.selectCard(cardId, false);
        }
        this.refreshSelectionOrder();
        this.refreshSelectability();
        this.updateTitle(true);
        this.updateButtons();
    }

    refreshSelectionOrder() {
        // Clear all previous orders in hand
        document.querySelectorAll('#my-hand-passengers .buu_card .buu_card-selection-order').forEach(el => el.textContent = '');

        this.selectedIds.forEach((id, idx) => {
            this.game.selectCard(id, true, idx + 1);
        });
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();
        if (this.selectedIds.length > 0) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm());
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        } else {
            this.bga.statusBar.addActionButton(_('Skip'), () => this.onSkip(), { color: 'gray' });
        }
    }

    onConfirm() {
        this.bga.actions.performAction("actPlayFromHand", { passengerIds: this.selectedIds.join(',') });
    }

    onSkip() {
        this.bga.actions.performAction("actSkip");
    }

    onCancel() {
        this.selectedIds.forEach(id => this.game.selectCard(id, false));
        this.selectedIds = [];
        this.refreshSelectability();
        this.updateTitle(true);
        this.updateButtons();
    }
}

class Penalty {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedId = null;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.bga.statusBar.setTitle(isCurrentPlayerActive ? 
            _('${you} must select a card to move to Unhappies as a penalty') :
            _('${actplayer} must take a penalty')
        );
      
        if (isCurrentPlayerActive) {
            this.updateButtons();
            this.game.setSelectable('any', true, 'hand-container');
        }
    }

    onLeavingState() {
        this.game.setSelectable('any', false);
        this.selectedId = null;
    }

    onCardClick(cardId) {
        this.game.clearCardSelection();
        this.selectedId = cardId;
        this.game.selectCard(cardId, true);
        this.updateButtons();
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();
        if (this.selectedId) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm());
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        }
    }

    onConfirm() {
        this.bga.actions.performAction("actChoosePenalty", { cardId: this.selectedId });
    }

    onCancel() {
        this.game.selectCard(this.selectedId, false);
        this.selectedId = null;
        this.updateButtons();
    }
}

class CorrupterSwap {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedHandId = null;
        this.selectedTargetId = null;
        this.args = null;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.args = args;
        this.updateTitle(isCurrentPlayerActive);
      
        if (isCurrentPlayerActive) {
            this.updateButtons();
            this.refreshSelectability();
        }
    }

    updateTitle(isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${actplayer} is using a Corrupter'));
            return;
        }

        if (!this.selectedHandId) {
            this.bga.statusBar.setTitle(_('${you} may select a passenger from your hand to swap'));
        } else if (!this.selectedTargetId) {
            this.bga.statusBar.setTitle(_('${you} must select a target passenger from Waiting Room or a matching Bus'));
        } else {
            this.bga.statusBar.setTitle(_('${you} have selected the cards. Click Confirm to swap.'));
        }
    }

    refreshSelectability() {
        this.game.setSelectable('any', false);
        
        // Hand passengers are ALWAYS selectable to allow changing mind
        this.game.setSelectable('passenger', true, 'my-hand-passengers');

        if (this.selectedHandId) {
            this.game.selectCard(this.selectedHandId, true);
            
            const handTypeArg = this.game.getCardTypeArg(this.selectedHandId);
            const handColor = Math.floor(handTypeArg / 10);
            const handAbility = handTypeArg % 10;

            // 1. All passengers in Waiting Room (any color)
            document.querySelectorAll('#waiting-room .buu_card').forEach(el => {
                if (el.dataset.id != this.args.corrupterId) {
                    el.classList.add('buu_selectable');
                }
            });

            // 2. Passengers in buses if color matches
            document.querySelectorAll('.buu_bus-inner .buu_card').forEach(el => {
                const cardId = el.dataset.id;
                if (cardId == this.args.corrupterId) return; // (not themselves)

                // Find the bus this passenger is in
                const busStack = el.closest('.buu_bus-stack');
                if (busStack) {
                    const busId = busStack.id.replace('bus-stack-', '');
                    const busTypeArg = this.game.getCardTypeArg(busId);
                    const busColor = Math.floor(busTypeArg / 10);

                    if (handColor === busColor || handAbility === 6) {
                        el.classList.add('buu_selectable');
                    }
                }
            });
        }
    }

    onLeavingState() {
        this.game.setSelectable('any', false);
        this.selectedHandId = null;
        this.selectedTargetId = null;
        this.args = null;
    }

    onCardClick(cardId, type, containerId) {
        if (type !== 'passenger') return;

        if (containerId === 'my-hand-passengers') {
            // If clicking another hand card, change selection
            if (this.selectedHandId === cardId) {
                // Deselect if same
                this.selectedHandId = null;
                this.selectedTargetId = null;
                this.game.selectCard(cardId, false);
            } else {
                // Change selection
                if (this.selectedHandId) {
                    this.game.selectCard(this.selectedHandId, false);
                }
                if (this.selectedTargetId) {
                    this.game.selectCard(this.selectedTargetId, false);
                }
                this.selectedHandId = cardId;
                this.selectedTargetId = null; // Reset target when hand card changes
                this.game.selectCard(cardId, true);
            }
            this.refreshSelectability();
            this.updateTitle(true);
            this.updateButtons();
            return;
        }

        if (!this.selectedHandId) return; // Cannot select target without hand card

        // Selecting target
        const el = document.getElementById(`card-${cardId}`);
        if (el && el.classList.contains('buu_selectable')) {
            if (this.selectedTargetId === cardId) {
                this.selectedTargetId = null;
                this.game.selectCard(cardId, false);
            } else {
                if (this.selectedTargetId) {
                    this.game.selectCard(this.selectedTargetId, false);
                }
                this.selectedTargetId = cardId;
                this.game.selectCard(cardId, true);
            }
            this.updateTitle(true);
            this.updateButtons();
        }
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();
        if (this.selectedHandId && this.selectedTargetId) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm());
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        } else {
            this.bga.statusBar.addActionButton(_('Skip'), () => this.onSkip(), { color: 'gray' });
        }
    }

    onConfirm() {
        this.bga.actions.performAction("actSwap", { handCardId: this.selectedHandId, targetCardId: this.selectedTargetId });
    }

    onSkip() {
        this.bga.actions.performAction("actSkip");
    }

    onCancel() {
        if (this.selectedTargetId) this.game.selectCard(this.selectedTargetId, false);
        if (this.selectedHandId) this.game.selectCard(this.selectedHandId, false);
        this.selectedHandId = null;
        this.selectedTargetId = null;
        this.refreshSelectability();
        this.updateButtons();
        this.updateTitle(true);
    }
}

class DrunkardChoice {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedIds = [];
        this.args = null;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.args = args;
        this.bga.statusBar.setTitle(isCurrentPlayerActive ? 
            _('${you} must select 2 victims for the Drunkard to send to Unhappies') :
            _('${actplayer} is choosing victims for the Drunkard')
        );
      
        if (isCurrentPlayerActive) {
            this.updateButtons();
            
            // Highlight and expand the departing bus
            const busId = this.args.passengers[0].location_arg; // DrunkardChoice args use location_arg as bus_id
            const busEl = document.getElementById(`card-${busId}`);
            const innerEl = document.getElementById(`bus-${busId}-inner`);
            const stackEl = document.getElementById(`bus-stack-${busId}`);
            
            if (stackEl) {
                stackEl.style.zIndex = '2000';
                stackEl.style.position = 'relative';
                stackEl.style.transform = 'scale(1.1)';
                stackEl.style.transition = 'transform 0.3s';
            }
            
            if (innerEl) {
                innerEl.style.zIndex = '2001';
                innerEl.style.display = 'flex';
                innerEl.style.flexDirection = 'row';
                innerEl.style.flexWrap = 'wrap';
                innerEl.style.gap = '10px';
                innerEl.style.background = 'rgba(255, 255, 255, 0.9)';
                innerEl.style.padding = '15px';
                innerEl.style.borderRadius = '12px';
                innerEl.style.boxShadow = '0 0 20px rgba(0,0,0,0.6)';
                innerEl.style.marginTop = '20px';
                innerEl.style.minWidth = '300px';
                innerEl.style.width = 'max-content';
                
                innerEl.querySelectorAll('.buu_card').forEach(el => {
                    el.classList.add('buu_selectable');
                    el.style.position = 'static'; 
                    el.style.margin = '0'; // Clear the accordion margin-top
                });
            }
        }
    }

    onLeavingState() {
        const busId = this.args ? (this.args.passengers.length > 0 ? this.args.passengers[0].location_arg : null) : null;
        if (busId) {
            const stackEl = document.getElementById(`bus-stack-${busId}`);
            const innerEl = document.getElementById(`bus-${busId}-inner`);
            if (stackEl) {
                stackEl.style.zIndex = '';
                stackEl.style.position = '';
                stackEl.style.transform = '';
            }
            if (innerEl) {
                innerEl.style.display = '';
                innerEl.style.flexWrap = '';
                innerEl.style.gap = '';
                innerEl.style.background = '';
                innerEl.style.padding = '';
                innerEl.style.borderRadius = '';
                innerEl.style.boxShadow = '';
                innerEl.style.marginTop = '';
                innerEl.style.minWidth = '';
                innerEl.querySelectorAll('.buu_card').forEach(el => {
                    el.style.position = '';
                });
            }
        }
        this.selectedIds = [];
        document.querySelectorAll('.buu_card.buu_selectable').forEach(el => el.classList.remove('buu_selectable'));
        this.args = null;
    }

    onCardClick(cardId, type, containerId) {
        const index = this.selectedIds.indexOf(cardId);
        if (index === -1) {
            if (this.selectedIds.length < 2) {
                this.selectedIds.push(cardId);
                this.game.selectCard(cardId, true);
            }
        } else {
            this.selectedIds.splice(index, 1);
            this.game.selectCard(cardId, false);
        }
        this.updateButtons();
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();
        if (this.selectedIds.length === Math.min(2, this.args.passengers.length)) {
            this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm());
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        }
    }

    onConfirm() {
        this.bga.actions.performAction("actSelectVictims", { victimIds: this.selectedIds.join(',') });
    }

    onCancel() {
        this.selectedIds.forEach(id => this.game.selectCard(id, false));
        this.selectedIds = [];
        this.updateButtons();
    }
}

class RefillHand {
    constructor(game, bga) {
        this.game = game;
        this.bga = bga;
        this.selectedIds = [];
        this.args = null;
    }

    onEnteringState(args, isCurrentPlayerActive) {
        this.args = args;
        this.updateTitle(isCurrentPlayerActive);
      
        if (isCurrentPlayerActive) {
            this.updateButtons();
            this.game.setSelectable('bus', true, 'garage');
            this.game.setSelectable('passenger', true, 'waiting-room');
        }
    }

    updateTitle(isCurrentPlayerActive) {
        if (!isCurrentPlayerActive) {
            this.bga.statusBar.setTitle(_('${actplayer} is refilling hand'));
            return;
        }

        const selectedBuses = this.selectedIds.filter(id => this.game.getCardType(id) === 'bus').length;
        const selectedPassengers = this.selectedIds.filter(id => this.game.getCardType(id) === 'passenger').length;

        const neededBuses = this.args.missingBuses - selectedBuses;
        const neededPassengers = this.args.missingPassengers - selectedPassengers;

        let msg = _('${you} must refill your hand: ');
        let requirements = [];
        if (neededBuses > 0) requirements.push(_('${n} more bus(es)').replace('${n}', neededBuses));
        if (neededPassengers > 0) requirements.push(_('${n} more passenger(s)').replace('${n}', neededPassengers));
        
        if (requirements.length === 0) {
            msg = _('${you} have selected all required cards. Click Confirm.');
        } else {
            msg += requirements.join(' ' + _('and') + ' ');
        }

        this.bga.statusBar.setTitle(msg);
    }

    onLeavingState() {
        this.game.setSelectable('any', false);
        this.selectedIds = [];
        this.args = null;
    }

    onCardClick(cardId) {
        const index = this.selectedIds.indexOf(cardId);
        const type = this.game.getCardType(cardId);

        if (index === -1) {
            // Check limits before selecting
            const currentCount = this.selectedIds.filter(id => this.game.getCardType(id) === type).length;
            const limit = type === 'bus' ? this.args.missingBuses : this.args.missingPassengers;

            if (currentCount < limit) {
                this.selectedIds.push(cardId);
                this.game.selectCard(cardId, true);
            }
        } else {
            this.selectedIds.splice(index, 1);
            this.game.selectCard(cardId, false);
        }
        this.updateTitle(true);
        this.updateButtons();
    }

    updateButtons() {
        this.bga.statusBar.removeActionButtons();
        
        const selectedBuses = this.selectedIds.filter(id => this.game.getCardType(id) === 'bus').length;
        const selectedPassengers = this.selectedIds.filter(id => this.game.getCardType(id) === 'passenger').length;
        
        const isComplete = selectedBuses === this.args.missingBuses && selectedPassengers === this.args.missingPassengers;

        this.bga.statusBar.addActionButton(_('Confirm'), () => this.onConfirm(), { 
            color: isComplete ? 'primary' : 'secondary',
            disabled: !isComplete 
        });

        if (this.selectedIds.length > 0) {
            this.bga.statusBar.addActionButton(_('Cancel'), () => this.onCancel(), { color: 'gray' });
        }
    }

    onConfirm() {
        this.bga.actions.performAction("actRefill", { cardIds: this.selectedIds.join(',') });
    }

    onCancel() {
        this.selectedIds.forEach(id => this.game.selectCard(id, false));
        this.selectedIds = [];
        this.updateTitle(true);
        this.updateButtons();
    }
}

export class Game {
    constructor(bga) {
        this.bga = bga;

        // Declare the State classes
        this.activeBus = new ActiveBus(this, bga);
        this.bga.states.register('ActiveBus', this.activeBus);

        this.waitingRoom = new WaitingRoom(this, bga);
        this.bga.states.register('WaitingRoom', this.waitingRoom);

        this.travelersHand = new TravelersHand(this, bga);
        this.bga.states.register('TravelersHand', this.travelersHand);

        this.penalty = new Penalty(this, bga);
        this.bga.states.register('Penalty', this.penalty);

        this.refillHand = new RefillHand(this, bga);
        this.bga.states.register('RefillHand', this.refillHand);

        this.drunkardChoice = new DrunkardChoice(this, bga);
        this.bga.states.register('DrunkardChoice', this.drunkardChoice);

        this.corrupterSwap = new CorrupterSwap(this, bga);
        this.bga.states.register('CorrupterSwap', this.corrupterSwap);
    }
    
    setup( gamedatas ) {
        this.gamedatas = gamedatas;

        // Main game area
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="bus-game-container">
                <div id="top-side">
                    <h3>My Hand</h3>
                    <div id="hand-container" class="buu_hand-container">
                        <div id="my-hand-buses" class="buu_hand-zone"></div>
                        <div id="my-hand-passengers" class="buu_hand-zone"></div>
                    </div>
                </div>
                <div id="bottom-layout">
                    <div id="left-side">
                        <h3>Waiting Room</h3>
                        <div id="waiting-room"></div>
                    </div>
                    <div id="center-side">
                        <h3>Platform</h3>
                        <div id="platform"></div>
                    </div>
                    <div id="right-side">
                        <h3>Garage</h3>
                        <div id="garage"></div>
                    </div>
                </div>
            </div>
            <div id="player-tables"></div>
        `);
        
        // Setting up player boards
        Object.values(gamedatas.players).forEach(player => {
            this.bga.playerPanels.getElement(player.id).insertAdjacentHTML('beforeend', `
                <div class="buu_player-panel-stats">
                    <div class="buu_stat-row">
                        <i class="fa fa-frown-o"></i> Unhappy: <span id="unhappy-counter-${player.id}">${player.unhappy_points}</span>
                    </div>
                </div>
            `);

            document.getElementById('player-tables').insertAdjacentHTML('beforeend', `
                <div id="player-table-${player.id}" class="buu_player-table">
                    <h4>${player.name}</h4>
                    <div class="buu_player-zones">
                        <div class="buu_zone">
                            <h5>Passengers</h5>
                            <div id="passengers-zone-${player.id}" class="buu_card-zone"></div>
                        </div>
                        <div class="buu_zone">
                            <h5>Unhappies</h5>
                            <div id="unhappies-zone-${player.id}" class="buu_card-zone"></div>
                        </div>
                        <div class="buu_zone">
                            <h5>Departed Buses</h5>
                            <div id="departed-buses-zone-${player.id}" class="buu_card-zone"></div>
                        </div>
                    </div>
                </div>
            `);
        });
        
        // Render initial cards
        this.renderCards(gamedatas);

        // Setup help button
        this.setupHelp();

        // Setup game notifications
        this.setupNotifications();

        this.setupTooltips();

    }

    setupTooltips() {
        // Tooltips for passenger types
        Object.entries(this.gamedatas.passenger_types).forEach(([type, data]) => {
            const nodes = document.querySelectorAll(`.buu_card.buu_passenger.buu_arg-${type}`);
            nodes.forEach(node => {
                this.bga.gameui.addTooltip(node.id, `<b>${data.name}</b><br/>${data.description}`, '');
            });
        });
        
        // Tooltips for buses
        const buses = document.querySelectorAll('.buu_card.buu_bus');
        buses.forEach(node => {
            const classList = Array.from(node.classList);
            const argClass = classList.find(c => c.startsWith('buu_arg-'));
            if (argClass) {
                const cap = argClass.split('-')[1];
                this.bga.gameui.addTooltip(node.id, `<b>Bus</b><br/>Capacity: ${cap} passengers`, '');
            }
        });
    }

    setupHelp() {
        this.bga.gameArea.getElement().insertAdjacentHTML('beforeend', `
            <div id="help-button">?</div>
        `);
        
        document.getElementById('help-button').addEventListener('click', () => {
            this.showHelp();
        });
    }

    showHelp() {
        const dialog = new ebg.popindialog();
        dialog.create('bgaHelpDialog');
        dialog.setTitle(_("How to Play - BUUUUUUUUS"));
        dialog.setContent(this.getHelpHtml());
        dialog.show();
        
        const underlayId = 'popin_bgaHelpDialog_underlay';
        if (document.getElementById(underlayId)) {
            document.getElementById(underlayId).addEventListener('click', () => dialog.hide());
        }
    }

    getHelpHtml() {
        return `
            <div class="buu_help-content">
                <h3>${_("Turn Structure (6 Mini-phases)")}</h3>
                <ul>
                    <li><strong>${_("1. Active Bus")}:</strong> ${_("Play a Bus from your hand to the Platform, or select a Bus already on the Platform.")}</li>
                    <li><strong>${_("2. The Waiting Room")}:</strong> ${_("MANDATORY: Board all eligible passengers (matching color) from the bottom row of the Waiting Room columns.")}</li>
                    <li><strong>${_("3. The Travelers Hand")}:</strong> ${_("OPTIONAL: Play matching color Passenger cards from your hand.")}</li>
                    <li><strong>${_("4. Penalty")}:</strong> ${_("If you played a Bus but NO passengers boarded, you must discard a Passenger from your hand to the Unhappies.")}</li>
                    <li><strong>${_("5. Complete your Hand")}:</strong> ${_("Refill your hand to 7 Passengers and 2 Buses.")}</li>
                    <li><strong>${_("6. Next Player")}:</strong> ${_("Refill the Garage and the Waiting Room.")}</li>
                </ul>

                <h3>${_("Passenger Abilities")}</h3>
                <ul>
                    <li><span class="buu_help-ability-name">${_("Anonymous")}</span>: ${_("No special skill.")}</li>
                    <li><span class="buu_help-ability-name">${_("Surfer")}</span>: ${_("Needs at least 2 empty spaces to board.")}</li>
                    <li><span class="buu_help-ability-name">${_("Lovers")}</span>: ${_("Always travel in pairs. If a bus reaches its max capacity with only one lover on board, the bus 'stalls' until the partner joins.")}</li>
                    <li><span class="buu_help-ability-name">${_("General")}</span>: ${_("Forces the bus to depart immediately. If a Lover is alone on the bus, they go to the Unhappies (a pair stays safe)!")}</li>
                    <li><span class="buu_help-ability-name">${_("Star")}</span>: ${_("At the start of your turn, you can call a bus from the Garage or another player's hand.")}</li>
                    <li><span class="buu_help-ability-name">${_("Drunkard")}</span>: ${_("When the bus departs, he sends 2 other passengers from that bus to the Unhappies.")}</li>
                    <li><span class="buu_help-ability-name">${_("Backpacker")}</span>: ${_("Wild color. Can board any bus.")}</li>
                    <li><span class="buu_help-ability-name">${_("Corrupter")}</span>: ${_("When boarding, you can swap a card from your hand with one in the Waiting Room or on the Platform.")}</li>
                    <li><span class="buu_help-ability-name">${_("Baby")}</span>: ${_("Gets priority in the Waiting Room, moving directly to the bottom (Row 4).")}</li>
                </ul>
                
                <h3>${_("Scoring")}</h3>
                <ul>
                    <li>${_("+1 point per Passenger successfully transported.")}</li>
                    <li>${_("-1 point per Passenger in the Unhappies or still in hand.")}</li>
                    <li>${_("-Capacity points per Bus card remaining in hand.")}</li>
                    <li>${_("+4 points per pair of same-color Lovers.")}</li>
                </ul>
            </div>
        `;
    }

    renderCards(gamedatas) {
        // Garage
        gamedatas.garage.forEach(card => this.createCardElement(card, 'garage'));

        // Waiting Room
        gamedatas.waiting_room.forEach(card => {
            this.createCardElement(card, 'waiting-room', card.location_arg);
        });

        // Hand
        gamedatas.hand.forEach(card => {
            const containerId = card.type === 'bus' ? 'my-hand-buses' : 'my-hand-passengers';
            this.createCardElement(card, containerId);
        });

        // Platform
        gamedatas.platform_buses.forEach(bus => {
            this.addBusToPlatform(bus);
            
            const passengers = gamedatas.platform_passengers.filter(p => p.location_arg == bus.id);
            passengers.forEach(p => this.createCardElement(p, `bus-${bus.id}-inner`));
        });

        // Other zones
        gamedatas.passengers_zone.forEach(card => this.createCardElement(card, `passengers-zone-${card.location_arg}`));
        gamedatas.unhappies_zone.forEach(card => this.createCardElement(card, `unhappies-zone-${card.location_arg}`));
        gamedatas.departed_buses.forEach(card => this.createCardElement(card, `departed-buses-zone-${card.location_arg}`));
    }

    addBusToPlatform(bus) {
        const id = bus.id || bus.card_id;
        
        // 1. Ensure stack wrapper exists
        let stackEl = document.getElementById(`bus-stack-${id}`);
        if (!stackEl) {
            document.getElementById('platform').insertAdjacentHTML('beforeend', `
                <div id="bus-stack-${id}" class="buu_bus-stack">
                    <button id="bus-toggle-${id}" class="buu_bus-toggle-btn"><i class="fa fa-expand"></i></button>
                    <div id="bus-${id}-inner" class="buu_bus-inner"></div>
                </div>
            `);
            stackEl = document.getElementById(`bus-stack-${id}`);
            
            const toggleBtn = document.getElementById(`bus-toggle-${id}`);
            if (toggleBtn) {
                toggleBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleBusStack(id);
                });
            }
        }

        const innerEl = document.getElementById(`bus-${id}-inner`);

        // 2. Move or create card
        let el = document.getElementById(`card-${id}`);
        if (el) {
            // Move existing element (e.g. from hand)
            el.classList.remove('buu_selected', 'buu_selectable', 'buu_unselectable-visual');
            const orderEl = el.querySelector('.buu_card-selection-order');
            if (orderEl) orderEl.textContent = '';
            
            stackEl.insertBefore(el, innerEl);
            el.style.gridColumn = '';
            el.style.gridRow = '';
        } else {
            // Create new element
            this.createCardElement(bus, `bus-stack-${id}`);
            el = document.getElementById(`card-${id}`);
            stackEl.insertBefore(el, innerEl);
        }
    }

    toggleBusStack(busId) {
        const inner = document.getElementById(`bus-${busId}-inner`);
        const stack = document.getElementById(`bus-stack-${busId}`);
        if (inner) {
            inner.classList.toggle('buu_expanded');
            const isExpanded = inner.classList.contains('buu_expanded');
            
            if (stack) {
                stack.style.zIndex = isExpanded ? '5000' : '';
            }

            const btn = document.querySelector(`#bus-stack-${busId} .buu_bus-toggle-btn i`);
            if (btn) {
                btn.classList.toggle('fa-expand', !isExpanded);
                btn.classList.toggle('fa-compress', isExpanded);
            }
        }
    }

    createCardElement(card, containerId, position = null) {
        // Robust handling of both object (type, type_arg) and raw row (card_type, card_type_arg)
        const type = card.type || card.card_type;
        const typeArg = card.type_arg !== undefined ? card.type_arg : card.card_type_arg;
        const cardId = card.id || card.card_id;
        const locationArg = card.location_arg !== undefined ? card.location_arg : card.card_location_arg;

        if (type === undefined || typeArg === undefined) {
            console.error("Invalid card data for creation:", card);
            return;
        }

        const colorClass = `buu_color-${Math.floor(typeArg / 10)}`;
        const typeClass = `buu_${type}`;
        const arg = typeArg % 10;
        
        let content = '';
        if (type === 'bus') {
            content = `Cap: ${arg}`;
        } else {
            const abilityName = this.getAbilityName(arg);
            content = abilityName;
        }

        let style = '';
        if (containerId === 'waiting-room') {
            const pos = position || locationArg;
            const col = Math.floor(pos / 10);
            const row = pos % 10;
            style = `style="grid-column: ${col}; grid-row: ${row};"`;
        }

        const html = `
            <div id="card-${cardId}" class="buu_card ${typeClass} ${colorClass} buu_arg-${arg}" data-id="${cardId}" data-type="${type}" data-type-arg="${typeArg}" ${style}>
                <div class="buu_card-selection-order"></div>
            </div>
        `;

        const container = document.getElementById(containerId);
        if (container) {
            container.insertAdjacentHTML('beforeend', html);
            const el = document.getElementById(`card-${cardId}`);
            el.addEventListener('click', () => this.onCardClick(cardId));
        }
    }

    getAbilityName(abilityId) {
        const names = ['Anon', 'Surfer', 'Lovers', 'General', 'Star', 'Drunk', 'Backpack', 'Corrupt', 'Baby'];
        return names[abilityId] || 'Anon';
    }

    onCardClick(cardId) {
        const el = document.getElementById(`card-${cardId}`);
        if (!el.classList.contains('buu_selectable')) return;

        const type = el.dataset.type;
        const containerId = el.parentElement.id;

        const activeState = this.bga.states.getCurrentPlayerStateClass();
        if (activeState && activeState.onCardClick) {
            activeState.onCardClick(cardId, type, containerId);
        }
    }

    setSelectable(type, selectable, containerId = null) {
        const selector = containerId ? `#${containerId} .buu_card` : '.buu_card';
        document.querySelectorAll(selector).forEach(el => {
            if (type === 'any' || el.dataset.type === type) {
                el.classList.toggle('buu_selectable', selectable);
                if (!selectable) {
                    el.classList.remove('buu_unselectable-visual');
                }
            }
        });
    }

    selectCard(cardId, selected, order = null) {
        const el = document.getElementById(`card-${cardId}`);
        if (!el) return;
        el.classList.toggle('buu_selected', selected);
        
        // If it's a bus on the platform, toggle class on its stack container
        const stack = el.closest('.buu_bus-stack');
        if (stack) {
            stack.classList.toggle('buu_selected-stack', selected);
        }

        const orderEl = el.querySelector('.buu_card-selection-order');
        if (orderEl) {
            orderEl.textContent = order ? order : '';
        }
    }

    clearCardSelection() {
        document.querySelectorAll('.buu_card.buu_selected').forEach(el => {
            el.classList.remove('buu_selected');
            const orderEl = el.querySelector('.buu_card-selection-order');
            if (orderEl) orderEl.textContent = '';
        });
        document.querySelectorAll('.buu_bus-stack.buu_selected-stack').forEach(el => {
            el.classList.remove('buu_selected-stack');
        });
    }

    getCardType(cardId) {
        const el = document.getElementById(`card-${cardId}`);
        if (el && el.dataset.type) {
            return el.dataset.type;
        }

        const allCards = [
            ...this.gamedatas.hand,
            ...this.gamedatas.garage,
            ...this.gamedatas.waiting_room,
            ...this.gamedatas.platform_buses,
            ...this.gamedatas.platform_passengers,
            ...this.gamedatas.passengers_zone,
            ...this.gamedatas.unhappies_zone,
            ...this.gamedatas.departed_buses
        ];
        const card = allCards.find(c => c.id == cardId);
        return card ? (card.type || card.card_type) : 'passenger';
    }

    getCardTypeArg(cardId) {
        const el = document.getElementById(`card-${cardId}`);
        if (el && el.dataset.typeArg !== undefined) {
            return parseInt(el.dataset.typeArg);
        }

        const allCards = [
            ...this.gamedatas.hand,
            ...this.gamedatas.garage,
            ...this.gamedatas.waiting_room,
            ...this.gamedatas.platform_buses,
            ...this.gamedatas.platform_passengers,
            ...this.gamedatas.passengers_zone,
            ...this.gamedatas.unhappies_zone,
            ...this.gamedatas.departed_buses
        ];
        const card = allCards.find(c => c.id == cardId);
        return card ? parseInt(card.type_arg || card.card_type_arg) : 0;
    }

    getBusOccupancy(busId) {
        let occupancy = 0;
        const busEl = document.getElementById(`card-${busId}`);
        if (!busEl) return 0;
        
        const inner = document.getElementById(`bus-${busId}-inner`);
        if (inner) {
            inner.querySelectorAll('.buu_card').forEach(pEl => {
                const typeArg = parseInt(pEl.dataset.typeArg);
                const ability = typeArg % 10;
                occupancy += this.gamedatas.passenger_types[ability].capacity;
            });
        }
        return occupancy;
    }

    isBusStalled(busId) {
        const busEl = document.getElementById(`card-${busId}`);
        if (!busEl) return false;
        
        const typeArg = parseInt(busEl.dataset.typeArg);
        const capacity = typeArg % 10;
        const occupancy = this.getBusOccupancy(busId);
        
        if (occupancy < capacity) return false;

        let hasGeneral = false;
        let loverCount = 0;
        const inner = document.getElementById(`bus-${busId}-inner`);
        if (inner) {
            inner.querySelectorAll('.buu_card').forEach(pEl => {
                const pTypeArg = parseInt(pEl.dataset.typeArg);
                const ability = pTypeArg % 10;
                if (ability === 3) hasGeneral = true; // PASSENGER_GENERAL
                if (ability === 2) loverCount++; // PASSENGER_LOVERS
            });
        }

        return !hasGeneral && (loverCount % 2 !== 0);
    }

    setupNotifications() {
        this.bga.notifications.setupPromiseNotifications({
            busDeparted: 1000,
            passengerToUnhappies: 1000,
            busStalled: 1000,
            busPlaced: 1000,
            busSelected: 1000,
            passengerBoarded: 1000,
            passengerPlayed: 1000,
            garageRefilled: 1000,
            starUsed: 1000,
            babiesPrioritized: 1000,
            waitingRoomRefilled: 1000,
            waitingRoomSlid: 1000,
            handRefilled: 1000,
            corrupterSwap: 1000,
            drunkardVictimsSelected: 1000,
            penaltyTaken: 1000,
            scoring: 3000,
            newRound: 1000,
            newHand: 1000,
        });
    }

    notif_penaltyTaken(args) {
        const playerId = args.player_id;
        const cardId = args.card_id;
        const el = document.getElementById(`card-${cardId}`);
        
        if (el) {
            this.selectCard(cardId, false);
            document.getElementById(`unhappies-zone-${playerId}`).appendChild(el);
            el.style.gridColumn = '';
            el.style.gridRow = '';
        }

        const counter = document.getElementById(`unhappy-counter-${playerId}`);
        if (counter) {
            counter.textContent = args.new_unhappy_points;
        }

        this.bga.sounds.play('move');
    }

    notif_drunkardVictimsSelected(args) {
        const playerId = args.player_id;
        
        // 1. Move victims to Unhappies
        args.victim_ids.forEach(vId => {
            const el = document.getElementById(`card-${vId}`);
            if (el) {
                this.selectCard(vId, false);
                document.getElementById(`unhappies-zone-${playerId}`).appendChild(el);
                const counter = document.getElementById(`unhappy-counter-${playerId}`);
                if (counter) counter.textContent = parseInt(counter.textContent) + 1;
            }
        });

        // 2. Move rest of passengers to DashBoard (passengers zone)
        const busInner = document.getElementById(`bus-${args.bus_id}-inner`);
        if (busInner) {
            const passengers = busInner.querySelectorAll('.buu_card');
            passengers.forEach(pEl => {
                document.getElementById(`passengers-zone-${playerId}`).appendChild(pEl);
            });
        }

        // 3. Move bus to Departed
        const busEl = document.getElementById(`card-${args.bus_id}`);
        if (busEl) {
            document.getElementById(`departed-buses-zone-${playerId}`).appendChild(busEl);
            busEl.classList.remove('buu_selected', 'buu_selectable', 'buu_stalled-visual');
        }

        // 4. Cleanup stack and toggle
        const toggleEl = document.getElementById(`bus-toggle-${args.bus_id}`);
        if (toggleEl) toggleEl.remove();

        const stackEl = document.getElementById(`bus-stack-${args.bus_id}`);
        if (stackEl) stackEl.remove();

        const innerEl = document.getElementById(`bus-${args.bus_id}-inner`);
        if (innerEl) innerEl.remove();

        this.bga.sounds.play('move');
    }

    notif_corrupterSwap(args) {
        const isMe = args.player_id == this.bga.players.getCurrentPlayerId();
        
        let handEl = document.getElementById(`card-${args.hand_card_id}`);
        let targetEl = document.getElementById(`card-${args.target_card_id}`);

        // 1. Ensure elements exist
        if (!handEl) {
            // Hand card coming from someone else's hand
            this.createCardElement(args.hand_card_data, 'center-side'); // Temp parent
            handEl = document.getElementById(`card-${args.hand_card_id}`);
        }
        if (!targetEl) {
            // Target card (should exist, but just in case)
            this.createCardElement(args.target_card_data, 'center-side');
            targetEl = document.getElementById(`card-${args.target_card_id}`);
        }

        const handParent = handEl.parentElement;
        const targetParent = targetEl.parentElement;
        const targetStyle = targetEl.getAttribute('style');

        // 2. Perform Swap
        this.selectCard(args.hand_card_id, false);
        this.selectCard(args.target_card_id, false);
        
        // Hand card moves to target location
        targetParent.appendChild(handEl);
        if (targetStyle) {
            handEl.setAttribute('style', targetStyle);
        } else {
            handEl.style.gridColumn = '';
            handEl.style.gridRow = '';
        }

        // Target card moves to hand (or removed if not me)
        if (isMe) {
            const type = this.getCardType(args.target_card_id);
            const containerId = type === 'bus' ? 'my-hand-buses' : 'my-hand-passengers';
            document.getElementById(containerId).appendChild(targetEl);
            targetEl.style.gridColumn = '';
            targetEl.style.gridRow = '';
        } else {
            targetEl.remove();
        }

        this.bga.sounds.play('move');
    }

    notif_busPlaced(args) {
        this.addBusToPlatform(args.bus_data);
        this.bga.sounds.play('move');
    }

    notif_busSelected(args) {
        // Just a visual highlight or log
        this.bga.sounds.play('move');
    }

    notif_passengerBoarded(args) {
        this.handleBoarding(args.bus_id, [args.passenger_id]);
    }

    notif_passengerPlayed(args) {
        const pId = args.passenger_id;
        let pEl = document.getElementById(`card-${pId}`);
        if (!pEl) {
            // Passenger was in someone else's hand, need to create it
            this.createCardElement(args.passenger_data, 'center-side'); // Temporary container
            pEl = document.getElementById(`card-${pId}`);
        }
        this.handleBoarding(args.bus_id, [pId]);
    }

    notif_busDeparted(args) {
        const playerId = args.player_id;
        const busId = args.bus_id;
        const busEl = document.getElementById(`card-${busId}`);
        const stackEl = document.getElementById(`bus-stack-${busId}`);
        const toggleEl = document.getElementById(`bus-toggle-${busId}`);

        // 1. Move passengers out of the stack FIRST
        args.passenger_ids.forEach(pId => {
            const pEl = document.getElementById(`card-${pId}`);
            if (pEl) {
                document.getElementById(`passengers-zone-${playerId}`).appendChild(pEl);
                pEl.classList.remove('buu_selected', 'buu_selectable', 'buu_unselectable-visual');
            }
        });

        // 2. Move bus to Departed
        if (busEl) {
            document.getElementById(`departed-buses-zone-${playerId}`).appendChild(busEl);
            busEl.classList.remove('buu_selected', 'buu_selectable', 'buu_stalled-visual');
        }

        // 3. Cleanup stack and toggle
        if (toggleEl) {
            toggleEl.remove();
        }

        if (stackEl) {
            stackEl.remove();
        }

        const innerEl = document.getElementById(`bus-${busId}-inner`);
        if (innerEl) innerEl.remove();

        // 4. Re-arrange remaining buses in platform
        if (args.platform_buses) {
            args.platform_buses.forEach(bus => {
                const sEl = document.getElementById(`bus-stack-${bus.id}`);
                if (sEl) {
                    document.getElementById('platform').appendChild(sEl);
                }
            });
        }

        this.bga.sounds.play('move');
    }

    notif_passengerToUnhappies(args) {
        const pEl = document.getElementById(`card-${args.passenger_id}`);
        if (pEl) {
            document.getElementById(`unhappies-zone-${args.player_id}`).appendChild(pEl);
            const counter = document.getElementById(`unhappy-counter-${args.player_id}`);
            if (counter) counter.textContent = parseInt(counter.textContent) + 1;
        }
        this.bga.sounds.play('move');
    }

    notif_busStalled(args) {
        this.bga.gameui.showMessage(_('Bus stalls! A lover is missing their partner.'), 'error');
    }

    notif_garageRefilled(args) {
        args.newBuses.forEach(bus => {
            this.createCardElement(bus, 'garage');
        });

        // If current player is refilling hand, mark these new buses as selectable
        const activeState = this.bga.states.getCurrentPlayerStateName();
        if (activeState === 'RefillHand' && this.bga.players.isActive()) {
            this.setSelectable('bus', true, 'garage');
        }
        this.bga.sounds.play('move');
    }

    notif_starUsed(args) {
        const isMe = args.player_id == this.bga.players.getCurrentPlayerId();
        
        // 1. Add bus to platform (handles existing or new)
        this.addBusToPlatform(args.bus_data);
        
        const innerEl = document.getElementById(`bus-${args.bus_id}-inner`);

        // 2. Handle Star card movement into the bus
        let starEl = document.getElementById(`card-${args.star_id}`);
        if (!starEl) {
            // If it's not me, create the star card (since it was hidden in hand)
            this.createCardElement(args.star_data, `bus-${args.bus_id}-inner`);
            starEl = document.getElementById(`card-${args.star_id}`);
        }

        if (starEl) {
            this.selectCard(args.star_id, false);
            starEl.classList.remove('buu_selected', 'buu_selectable', 'buu_unselectable-visual');
            starEl.style.gridColumn = '';
            starEl.style.gridRow = '';
            innerEl.appendChild(starEl);
        }
        
        this.bga.sounds.play('move');
    }

    notif_handRefilled(args) {
        const isMe = args.player_id == this.bga.players.getCurrentPlayerId();
        
        args.card_ids.forEach(cardId => {
            const el = document.getElementById(`card-${cardId}`);
            if (el) {
                this.selectCard(cardId, false);
                el.classList.remove('buu_selected', 'buu_selectable', 'buu_unselectable-visual');

                if (isMe) {
                    const type = this.getCardType(cardId);
                    const containerId = type === 'bus' ? 'my-hand-buses' : 'my-hand-passengers';
                    document.getElementById(containerId).appendChild(el);
                    el.style.gridColumn = '';
                    el.style.gridRow = '';
                } else {
                    el.remove();
                }
            }
        });
        this.bga.sounds.play('move');
    }

    notif_babiesPrioritized(args) {
        args.waitingRoom.forEach(card => {
            const el = document.getElementById(`card-${card.id}`);
            if (el) {
                this.selectCard(card.id, false);
                const col = Math.floor(card.location_arg / 10);
                const row = card.location_arg % 10;
                el.style.gridColumn = col;
                el.style.gridRow = row;
            }
        });
        this.bga.sounds.play('move');
    }

    notif_waitingRoomSlid(args) {
        args.waitingRoom.forEach(card => {
            const el = document.getElementById(`card-${card.id}`);
            if (el) {
                this.selectCard(card.id, false);
                const col = Math.floor(card.location_arg / 10);
                const row = card.location_arg % 10;
                el.style.gridColumn = col;
                el.style.gridRow = row;
            }
        });
        this.bga.sounds.play('move');
    }

    notif_waitingRoomRefilled(args) {
        args.newCards.forEach(card => {
            this.createCardElement(card, 'waiting-room', card.location_arg);
        });

        // If current player is refilling hand, mark these new passengers as selectable
        const activeState = this.bga.states.getCurrentPlayerStateName();
        if (activeState === 'RefillHand' && this.bga.players.isActive()) {
            this.setSelectable('passenger', true, 'waiting-room');
        }
        this.bga.sounds.play('move');
    }

    handleBoarding(busId, passengerIds) {
        const busEl = document.getElementById(`card-${busId}`);
        if (busEl) {
            let inner = document.getElementById(`bus-${busId}-inner`);
            if (!inner) {
                const stack = busEl.parentElement;
                stack.insertAdjacentHTML('beforeend', `<div id="bus-${busId}-inner" class="buu_bus-inner"></div>`);
                inner = document.getElementById(`bus-${busId}-inner`);
            }
            passengerIds.forEach(pId => {
                const pEl = document.getElementById(`card-${pId}`);
                if (pEl) {
                    this.selectCard(pId, false);
                    pEl.classList.remove('buu_selected', 'buu_selectable', 'buu_unselectable-visual');
                    pEl.style.gridColumn = '';
                    pEl.style.gridRow = '';
                    inner.appendChild(pEl);
                }
            });
            this.bga.sounds.play('move');
        }
    }

    clearBoard() {
        // Clear card zones
        document.getElementById('my-hand-buses').innerHTML = '';
        document.getElementById('my-hand-passengers').innerHTML = '';
        document.getElementById('waiting-room').innerHTML = '';
        document.getElementById('platform').innerHTML = '';
        
        // Clear player boards for all players
        Object.keys(this.gamedatas.players).forEach(pId => {
            const passengersZone = document.getElementById(`passengers-zone-${pId}`);
            if (passengersZone) passengersZone.innerHTML = '';
            
            const unhappiesZone = document.getElementById(`unhappies-zone-${pId}`);
            if (unhappiesZone) unhappiesZone.innerHTML = '';
            
            const departedBusesZone = document.getElementById(`departed-buses-zone-${pId}`);
            if (departedBusesZone) departedBusesZone.innerHTML = '';
            
            const unhappyCounter = document.getElementById(`unhappy-counter-${pId}`);
            if (unhappyCounter) unhappyCounter.textContent = '0';
        });
        
        // Also remove any residual card elements floating in the DOM
        document.querySelectorAll('.buu_card').forEach(el => el.remove());
    }

    notif_scoring(args) {
        // Update the score counters in the BGA top panel
        Object.keys(args.new_scores).forEach(pId => {
            const scoreCtrl = this.bga.gameui.scoreCtrl[pId];
            if (scoreCtrl) {
                scoreCtrl.toValue(args.new_scores[pId]);
            }
        });

        // Show a custom popup dialog summarizing the points
        const dialog = new ebg.popindialog();
        dialog.create('roundScoringDialog');
        dialog.setTitle(_("Round Scoring Details"));
        
        let html = `<div class="buu_scoring-popup">`;
        
        Object.keys(args.scores).forEach(pId => {
            const playerDetails = args.scores[pId];
            const playerName = this.gamedatas.players[pId].name;
            const playerColor = this.gamedatas.players[pId].color;
            
            html += `
                <div class="buu_player-scoring-block" style="border-left: 5px solid #${playerColor}; margin-bottom: 15px; padding-left: 10px;">
                    <h4 style="margin-top: 0; margin-bottom: 5px; color: #${playerColor};">${playerName}</h4>
                    <table class="buu_scoring-table" style="width: 100%; text-align: left; border-collapse: collapse;">
                        <tr><td>${_("Passengers Transported (+1 pt)")}:</td><td style="text-align: right; font-weight: bold; color: green;">+${playerDetails.passengers}</td></tr>
                        <tr><td>${_("Lovers Pairs Bonus (+4 pts)")}:</td><td style="text-align: right; font-weight: bold; color: green;">+${playerDetails.lovers_bonus}</td></tr>
                        <tr><td>${_("Night Buses (+1 pt/pass on platform)")}:</td><td style="text-align: right; font-weight: bold; color: green;">+${playerDetails.night_buses}</td></tr>
                        <tr><td>${_("Unhappy Passengers (-1 pt)")}:</td><td style="text-align: right; font-weight: bold; color: red;">${playerDetails.unhappies}</td></tr>
                        <tr><td>${_("Passengers in Hand (-1 pt)")}:</td><td style="text-align: right; font-weight: bold; color: red;">${playerDetails.hand_passengers}</td></tr>
                        <tr><td>${_("Buses in Hand (-capacity)")}:</td><td style="text-align: right; font-weight: bold; color: red;">${playerDetails.hand_buses}</td></tr>
                        <tr style="border-top: 1px solid #ccc; font-weight: bold;"><td>${_("Round Total")}:</td><td style="text-align: right; font-size: 16px;">${playerDetails.total >= 0 ? '+' : ''}${playerDetails.total}</td></tr>
                    </table>
                </div>
            `;
        });
        
        html += `</div>`;
        dialog.setContent(html);
        dialog.show();

        const underlayId = 'popin_roundScoringDialog_underlay';
        if (document.getElementById(underlayId)) {
            document.getElementById(underlayId).addEventListener('click', () => dialog.hide());
        }
    }

    notif_newRound(args) {
        this.clearBoard();
        
        // Render garage
        args.garage.forEach(card => this.createCardElement(card, 'garage'));
        
        // Render waiting room
        args.waiting_room.forEach(card => {
            this.createCardElement(card, 'waiting-room', card.location_arg);
        });
    }

    notif_newHand(args) {
        // Clear any old hand elements just in case
        document.getElementById('my-hand-buses').innerHTML = '';
        document.getElementById('my-hand-passengers').innerHTML = '';
        
        // Render new hand
        args.hand.forEach(card => {
            const containerId = card.type === 'bus' ? 'my-hand-buses' : 'my-hand-passengers';
            this.createCardElement(card, containerId);
        });
    }
}
