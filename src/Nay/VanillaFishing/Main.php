<?php

declare(strict_types=1);

namespace Nay\VanillaFishing;

use Nay\VanillaFishing\enchantment\FishingEnchantments;
use Nay\VanillaFishing\entity\FishingHook;
use Nay\VanillaFishing\listener\FishingListener;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

final class Main extends PluginBase{

	protected function onEnable() : void{
		$this->saveDefaultConfig();

		FishingEnchantments::bootstrap();
		FishingContext::boot($this);

		EntityFactory::getInstance()->register(FishingHook::class, function(World $world, CompoundTag $nbt) : FishingHook{
			return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
		}, ['FishingHook', EntityIds::FISHING_HOOK]);

		$this->getServer()->getPluginManager()->registerEvents(new FishingListener(), $this);
	}
}
