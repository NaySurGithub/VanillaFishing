<?php

declare(strict_types=1);

namespace Nay\VanillaFishing;

use Nay\VanillaFishing\loot\LootManager;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

final class FishingContext{

	private static ?self $instance = null;

	public readonly LootManager $loot;
	public readonly int $waitMin;
	public readonly int $waitMax;
	public readonly int $lureReduction;
	public readonly int $biteWindow;
	public readonly int $xpMin;
	public readonly int $xpMax;
	public readonly bool $noSkySlowdown;
	public readonly bool $rainSpeedup;
	public readonly bool $debug;

	private \Logger $logger;

	private function __construct(PluginBase $plugin){
		$config = $plugin->getConfig();
		$this->logger = $plugin->getLogger();
		$this->debug = (bool) $config->get("debug", false);
		$this->loot = new LootManager($plugin);

		$wait = $config->get("wait-time", []);
		$this->waitMin = (int) ($wait["min"] ?? 100);
		$this->waitMax = (int) ($wait["max"] ?? 600);
		$this->lureReduction = (int) ($wait["lure-reduction-per-level"] ?? 100);
		$this->noSkySlowdown = (bool) ($wait["no-sky-access-slowdown"] ?? true);
		$this->rainSpeedup = (bool) ($wait["rain-speedup"] ?? true);

		$this->biteWindow = (int) $config->get("bite-window", 10);

		$experience = $config->get("experience", []);
		$this->xpMin = (int) ($experience["min"] ?? 1);
		$this->xpMax = (int) ($experience["max"] ?? 6);
	}

	public static function boot(PluginBase $plugin) : void{
		self::$instance = new self($plugin);
	}

	public static function get() : self{
		return self::$instance ?? throw new \LogicException("FishingContext not booted");
	}

	public function debug(string $message) : void{
		if($this->debug){
			$this->logger->info(TextFormat::GRAY . "[Debug] " . $message);
		}
	}
}
