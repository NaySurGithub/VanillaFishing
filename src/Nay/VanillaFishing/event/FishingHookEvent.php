<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\event;

use Nay\VanillaFishing\entity\FishingHook;
use pocketmine\event\Event;
use pocketmine\player\Player;

abstract class FishingHookEvent extends Event{

	public function __construct(
		protected Player $player,
		protected FishingHook $hook
	){}

	public function getPlayer() : Player{
		return $this->player;
	}

	public function getHook() : FishingHook{
		return $this->hook;
	}
}
