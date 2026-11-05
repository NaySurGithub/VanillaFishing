# VanillaFishing

A 100% vanilla-like fishing system for PocketMine-MP 5.

## Features

- Fully working fishing rod: cast a bobber onto water and reel it back in.
- Vanilla wait-time mechanics (5-30s), reduced by **Lure**, slowed without sky access.
- Fish approach particle trail, splash/bite, and a bite window to reel in.
- Vanilla loot: **fish**, **junk** and **treasure** categories with correct weights.
- **Luck of the Sea** increases treasure chance; treasure requires open water.
- Jungle-biome fish pool, treasure gear/books enchanted on catch.
- Hooking and reeling mobs and other entities.
- Correct fishing-rod durability costs (catch / block / item / entity).
- Runtime-registered **Lure** and **Luck of the Sea** enchantments (with books).
- Experience orbs on catch (Mending repairs the rod automatically).

## Configuration

Everything is configurable in `config.yml`: wait times, bite window, category
chances, per-item loot weights (with counts, damage ranges and enchant flags),
experience amounts, and a `debug` flag that logs the full fishing lifecycle.

## Developer API

Listen to these events in the `Nay\VanillaFishing\event` namespace:

| Event | Cancellable | Description |
|-------|-------------|-------------|
| `FishingCastEvent` | yes | A player casts a line |
| `FishingBiteEvent` | no | A fish bites the bobber |
| `FishingCatchEvent` | yes | Loot and experience are about to be given (editable) |
| `FishingReelEntityEvent` | yes | A hooked entity is about to be reeled |

```php
public function onCatch(\Nay\VanillaFishing\event\FishingCatchEvent $event) : void{
    $event->setExperience($event->getExperience() * 2);
}
```

## Author

Nay
