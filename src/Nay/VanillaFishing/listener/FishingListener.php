<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\listener;

use Nay\VanillaFishing\entity\FishingHook;
use Nay\VanillaFishing\event\FishingCastEvent;
use Nay\VanillaFishing\FishingContext;
use pocketmine\entity\Location;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemUseEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\FishingRod;
use pocketmine\player\Player;

final class FishingListener implements Listener{

	/** @var array<int, FishingHook> */
	private array $hooks = [];

	/** @var array<int, int> */
	private array $lastUseTick = [];

	public function onItemUse(PlayerItemUseEvent $event) : void{
		if($event->getItem() instanceof FishingRod){
			$this->toggle($event->getPlayer());
		}
	}

	public function onInteract(PlayerInteractEvent $event) : void{
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK && $event->getItem() instanceof FishingRod){
			$this->toggle($event->getPlayer());
		}
	}

	private function toggle(Player $player) : void{
		$id = $player->getId();
		$tick = $player->getServer()->getTick();
		if(($this->lastUseTick[$id] ?? -1) === $tick){
			return;
		}
		$this->lastUseTick[$id] = $tick;

		if(isset($this->hooks[$id])){
			$hook = $this->hooks[$id];
			unset($this->hooks[$id]);
			if($hook->isAlive() && !$hook->isFlaggedForDespawn()){
				FishingContext::get()->debug($player->getName() . " reeled in");
				$hook->reelIn();
				return;
			}
		}

		$castEvent = new FishingCastEvent($player);
		$castEvent->call();
		if($castEvent->isCancelled()){
			FishingContext::get()->debug($player->getName() . "'s cast was cancelled by a plugin");
			return;
		}

		$location = $player->getLocation();
		$hook = new FishingHook(Location::fromObject($player->getEyePos(), $player->getWorld(), $location->yaw, $location->pitch), $player);
		$hook->setMotion($player->getDirectionVector()->multiply(1.5));
		$hook->spawnToAll();

		$this->hooks[$id] = $hook;
		FishingContext::get()->debug($player->getName() . " cast a fishing line");
	}

	public function onQuit(PlayerQuitEvent $event) : void{
		$id = $event->getPlayer()->getId();
		if(isset($this->hooks[$id])){
			$hook = $this->hooks[$id];
			if($hook->isAlive() && !$hook->isFlaggedForDespawn()){
				$hook->flagForDespawn();
			}
			unset($this->hooks[$id]);
		}
		unset($this->lastUseTick[$id]);
	}
}
