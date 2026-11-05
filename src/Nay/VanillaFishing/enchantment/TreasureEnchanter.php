<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\enchantment;

use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use function count;
use function max;
use function mt_rand;
use function shuffle;

final class TreasureEnchanter{

	public static function apply(Item $item) : void{
		$candidates = AvailableEnchantmentRegistry::getInstance()->getAllEnchantmentsForItem($item);
		if(count($candidates) === 0){
			return;
		}
		shuffle($candidates);

		$amount = mt_rand(1, 3);
		$applied = 0;
		foreach($candidates as $enchantment){
			if($applied >= $amount){
				break;
			}
			$item->addEnchantment(new EnchantmentInstance($enchantment, mt_rand(1, max(1, $enchantment->getMaxLevel()))));
			$applied++;
		}
	}
}
