# Pre-release Checklist: Move from Dev to Alpha

## License
- [ ] BGA must have a valid license for the game.

## Metadata and Game Assets
- [ ] **Game Information**: `gameinfos.jsonc` is up to date.
- [ ] **Game Box**: 3D version with transparent background.
- [ ] **Metadata Manager**: All pretty images added.
- [ ] **Cleanup**: No unused images in `img/` directory.
- [ ] **Sprites**: Multiple images (cards) compressed into sprites.
- [ ] **Size Limits**: Each image < 4MB; Total size < 15MB.
- [ ] **Fonts/Sounds**: Freeware licenses and correct directories (`fonts/`, `sounds/`).

## Server Side
- [ ] **Time Management**: Call `giveExtraTime()` when giving turn.
- [ ] **Progression**: `getGameProgression()` implemented.
- [ ] **Zombie Mode**: `zombie()` methods tested.
- [ ] **Statistics**: Meaningful stats implemented in `stats.jsonc`.
- [ ] **Notifications**: Meaningful and not excessive.
- [ ] **Tiebreaking**: Aux score field used and metadata updated.
- [ ] **Database**: No TRUNCATE or schema changes during gameplay.

## Client Side
- [ ] **Action Integrity**: `bgaPerformAction` only on player actions (not programmatic unless special cases).

## User Interface
- [ ] **Design Guidelines**: Adhere to BGA Studio Guidelines.
- [ ] **English Review**: Present tense, gender neutral, correct punctuation.
- [ ] **Centering**: Main game zone centered if space permits.
- [ ] **Zoom Quality**: High-res images with `background-size`.
- [ ] **Tooltips**: For all non-self-explanatory elements.
- [ ] **Translations**: Strings ready for translation.
- [ ] **Namespacing**: Prepend trigram (e.g., `mlk_`) to CSS classes to avoid conflicts.

## Special Testing
- [ ] **Minification**: Test with minified JS/CSS enabled.
- [ ] **Spectator Mode**: Public info visible, private info hidden.
- [ ] **Replay**: Test "in-game replay" and "full game replay" from start to end.
- [ ] **Browser Compatibility**: Chrome, Firefox, Edge, Safari.
- [ ] **Mobile**: Test responsive design/mobile view.
- [ ] **Realtime Mode**: Ensure clocks don't run out.

## Cleanup
- [ ] **Logs**: Remove `console.log` (JS) and debug logging (PHP).
- [ ] **Headers**: Copyright headers have developer name.
- [ ] **Folder Structure**: Clean root folder; move unnecessary files to `misc/`.

## Static Analysis
- [ ] Run "Dry run build" in Control Panel.
- [ ] Run "Check project" analysis.
