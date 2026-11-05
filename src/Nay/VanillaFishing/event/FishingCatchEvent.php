<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\event;

use Nay\VanillaFishing\entity\FishingHook;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\item\Item;
use pocketmine\player\Player;

final class FishingCatchEvent extends FishingHookEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		FishingHook $hook,
		private Item $loot,
		private int $experience,
		private int $luck,
		private bool $openWater
	){
		parent::__construct($player, $hook);
	}

	public function getLoot() : Item{
		return $this->loot;
	}

	public function setLoot(Item $loot) : void{
		$this->loot = $loot;
	}

	public function getExperience() : int{
		return $this->experience;
	}

	public function setExperience(int $experience) : void{
		$this->experience = $experience;
	}

	public function getLuck() : int{
		return $this->luck;
	}

	public function isOpenWater() : bool{
		return $this->openWater;
	}
}
