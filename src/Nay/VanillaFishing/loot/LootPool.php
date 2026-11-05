<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\loot;

use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use function count;
use function mt_rand;

final class LootPool{

	/** @var LootEntry[] */
	private array $entries;
	private int $totalWeight = 0;

	/**
	 * @param LootEntry[] $entries
	 */
	public function __construct(array $entries){
		$this->entries = $entries;
		foreach($entries as $entry){
			$this->totalWeight += $entry->getWeight();
		}
	}

	public function isEmpty() : bool{
		return count($this->entries) === 0 || $this->totalWeight <= 0;
	}

	public function roll() : Item{
		if($this->isEmpty()){
			return VanillaItems::RAW_FISH();
		}

		$roll = mt_rand(1, $this->totalWeight);
		$accumulated = 0;
		foreach($this->entries as $entry){
			$accumulated += $entry->getWeight();
			if($roll <= $accumulated){
				return $entry->create();
			}
		}

		return $this->entries[0]->create();
	}
}
