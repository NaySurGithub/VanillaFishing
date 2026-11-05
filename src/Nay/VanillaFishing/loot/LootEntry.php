<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\loot;

use Nay\VanillaFishing\enchantment\TreasureEnchanter;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use function min;
use function mt_rand;

final class LootEntry{

	/**
	 * @param array{int, int}|null $damageRange
	 */
	public function __construct(
		private Item $prototype,
		private int $weight,
		private int $count,
		private ?array $damageRange,
		private ?string $enchant
	){}

	public function getWeight() : int{
		return $this->weight;
	}

	public function create() : Item{
		$item = clone $this->prototype;
		$item->setCount($this->count);

		if($this->damageRange !== null && $item instanceof Durable){
			$percent = mt_rand($this->damageRange[0], $this->damageRange[1]);
			$damage = (int) ($item->getMaxDurability() * $percent / 100);
			$item->setDamage(min($damage, $item->getMaxDurability() - 1));
		}

		if($this->enchant === "gear" || $this->enchant === "book"){
			TreasureEnchanter::apply($item);
		}

		return $item;
	}
}
