<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\event;

use Nay\VanillaFishing\entity\FishingHook;
use pocketmine\entity\Entity;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\player\Player;

final class FishingReelEntityEvent extends FishingHookEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		Player $player,
		FishingHook $hook,
		private Entity $caught
	){
		parent::__construct($player, $hook);
	}

	public function getCaughtEntity() : Entity{
		return $this->caught;
	}
}
