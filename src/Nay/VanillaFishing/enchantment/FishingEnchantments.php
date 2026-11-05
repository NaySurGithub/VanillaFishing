<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\enchantment;

use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\ItemEnchantmentTags;
use pocketmine\item\enchantment\ItemFlags;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\enchantment\StringToEnchantmentParser;

final class FishingEnchantments{

	private static ?Enchantment $lure = null;
	private static ?Enchantment $luckOfTheSea = null;

	public static function lure() : Enchantment{
		return self::$lure ?? throw new \LogicException("FishingEnchantments not bootstrapped");
	}

	public static function luckOfTheSea() : Enchantment{
		return self::$luckOfTheSea ?? throw new \LogicException("FishingEnchantments not bootstrapped");
	}

	public static function bootstrap() : void{
		$idMap = EnchantmentIdMap::getInstance();

		$existingLure = $idMap->fromId(EnchantmentIds::LURE);
		if($existingLure instanceof Enchantment){
			self::$lure = $existingLure;
			self::$luckOfTheSea = $idMap->fromId(EnchantmentIds::LUCK_OF_THE_SEA);
			return;
		}

		self::$lure = new Enchantment("Lure", Rarity::RARE, ItemFlags::FISHING_ROD, ItemFlags::NONE, 3);
		self::$luckOfTheSea = new Enchantment("Luck of the Sea", Rarity::RARE, ItemFlags::FISHING_ROD, ItemFlags::NONE, 3);

		$idMap->register(EnchantmentIds::LURE, self::$lure);
		$idMap->register(EnchantmentIds::LUCK_OF_THE_SEA, self::$luckOfTheSea);

		$parser = StringToEnchantmentParser::getInstance();
		$parser->register("lure", fn() => self::$lure);
		$parser->register("luck_of_the_sea", fn() => self::$luckOfTheSea);

		$available = AvailableEnchantmentRegistry::getInstance();
		$available->register(self::$lure, [ItemEnchantmentTags::FISHING_ROD], []);
		$available->register(self::$luckOfTheSea, [ItemEnchantmentTags::FISHING_ROD], []);
	}
}
