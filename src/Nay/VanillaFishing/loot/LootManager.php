<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\loot;

use pocketmine\data\bedrock\BiomeIds;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\plugin\PluginBase;
use function count;
use function explode;
use function is_array;
use function max;
use function mt_rand;

final class LootManager{

	private const JUNGLE_BIOMES = [
		BiomeIds::JUNGLE,
		BiomeIds::JUNGLE_HILLS,
		BiomeIds::JUNGLE_EDGE,
		BiomeIds::BAMBOO_JUNGLE,
		BiomeIds::BAMBOO_JUNGLE_HILLS,
		BiomeIds::JUNGLE_MUTATED,
		BiomeIds::JUNGLE_EDGE_MUTATED,
	];

	private LootPool $fish;
	private LootPool $jungleFish;
	private LootPool $treasure;
	private LootPool $junk;

	private int $treasureBase;
	private int $junkBase;
	private int $treasurePerLuck;
	private int $junkPerLuck;
	private bool $openWaterRequired;

	public function __construct(PluginBase $plugin){
		$config = $plugin->getConfig();

		$loot = $config->get("loot", []);
		$this->fish = $this->buildPool($plugin, $loot["fish"] ?? []);
		$this->jungleFish = $this->buildPool($plugin, $loot["jungle-fish"] ?? []);
		$this->treasure = $this->buildPool($plugin, $loot["treasure"] ?? []);
		$this->junk = $this->buildPool($plugin, $loot["junk"] ?? []);

		$categories = $config->get("categories", []);
		$this->treasureBase = (int) ($categories["treasure-base"] ?? 5);
		$this->junkBase = (int) ($categories["junk-base"] ?? 10);
		$this->treasurePerLuck = (int) ($categories["treasure-per-luck"] ?? 2);
		$this->junkPerLuck = (int) ($categories["junk-per-luck"] ?? 2);
		$this->openWaterRequired = (bool) $config->get("open-water-required-for-treasure", true);
	}

	public function isOpenWaterRequired() : bool{
		return $this->openWaterRequired;
	}

	public function roll(int $biomeId, int $luck, bool $openWater) : Item{
		$treasureChance = $this->treasureBase + $luck * $this->treasurePerLuck;
		$junkChance = max(0, $this->junkBase - $luck * $this->junkPerLuck);

		if($this->openWaterRequired && !$openWater){
			$treasureChance = 0;
		}

		$roll = mt_rand(1, 100);
		if($roll <= $treasureChance && !$this->treasure->isEmpty()){
			return $this->treasure->roll();
		}
		if($roll <= $treasureChance + $junkChance && !$this->junk->isEmpty()){
			return $this->junk->roll();
		}

		if($this->isJungle($biomeId) && !$this->jungleFish->isEmpty()){
			return $this->jungleFish->roll();
		}
		return $this->fish->roll();
	}

	private function isJungle(int $biomeId) : bool{
		foreach(self::JUNGLE_BIOMES as $jungle){
			if($jungle === $biomeId){
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $definitions
	 */
	private function buildPool(PluginBase $plugin, $definitions) : LootPool{
		$entries = [];
		if(!is_array($definitions)){
			return new LootPool([]);
		}

		$parser = StringToItemParser::getInstance();
		foreach($definitions as $definition){
			if(!is_array($definition) || !isset($definition["item"])){
				continue;
			}
			$prototype = $parser->parse((string) $definition["item"]);
			if($prototype === null){
				$plugin->getLogger()->warning("Unknown loot item skipped: " . $definition["item"]);
				continue;
			}

			$damageRange = null;
			if(isset($definition["damage"])){
				$parts = explode("-", (string) $definition["damage"]);
				if(count($parts) === 2){
					$damageRange = [(int) $parts[0], (int) $parts[1]];
				}
			}

			$entries[] = new LootEntry(
				$prototype,
				(int) ($definition["weight"] ?? 1),
				(int) ($definition["count"] ?? 1),
				$damageRange,
				isset($definition["enchant"]) ? (string) $definition["enchant"] : null
			);
		}

		return new LootPool($entries);
	}
}
