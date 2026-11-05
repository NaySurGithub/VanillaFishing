<?php

declare(strict_types=1);

namespace Nay\VanillaFishing\entity;

use Nay\VanillaFishing\enchantment\FishingEnchantments;
use Nay\VanillaFishing\event\FishingBiteEvent;
use Nay\VanillaFishing\event\FishingCatchEvent;
use Nay\VanillaFishing\event\FishingReelEntityEvent;
use Nay\VanillaFishing\FishingContext;
use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\projectile\Projectile;
use pocketmine\item\FishingRod;
use pocketmine\item\VanillaItems;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\particle\BubbleParticle;
use pocketmine\world\sound\WaterSplashSound;
use function cos;
use function floor;
use function max;
use function mt_rand;
use function sin;
use function sqrt;
use const M_PI;

final class FishingHook extends Projectile{

	private const STATE_WAITING = 0;
	private const STATE_LURING = 1;
	private const STATE_BITING = 2;

	private bool $inWater = false;
	private int $state = self::STATE_WAITING;
	private ?int $waitTime = null;
	private int $lureTime = 0;
	private int $biteTime = 0;
	private ?Entity $hookedEntity = null;

	public static function getNetworkTypeId() : string{ return EntityIds::FISHING_HOOK; }

	protected function getInitialSizeInfo() : EntitySizeInfo{ return new EntitySizeInfo(0.25, 0.25); }

	protected function getInitialDragMultiplier() : float{ return 0.02; }

	protected function getInitialGravity() : float{ return 0.05; }

	public function canCollideWith(Entity $entity) : bool{
		return $entity !== $this->getOwningEntity() && parent::canCollideWith($entity);
	}

	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		$this->hookedEntity = $entityHit;
		$this->setMotion(Vector3::zero());
	}

	protected function despawnsOnEntityHit() : bool{
		return false;
	}

	protected function entityBaseTick(int $tickDiff = 1) : bool{
		parent::entityBaseTick($tickDiff);

		$owner = $this->getOwningEntity();
		if(!$owner instanceof Player || !$owner->isAlive() || !$owner->isConnected()){
			FishingContext::get()->debug("Bobber despawned: owner invalid");
			$this->flagForDespawn();
			return true;
		}
		if(!$owner->getInventory()->getItemInHand() instanceof FishingRod){
			FishingContext::get()->debug("Bobber despawned: " . $owner->getName() . " no longer holding a fishing rod");
			$this->flagForDespawn();
			return true;
		}
		if($owner->getWorld() !== $this->getWorld() || $owner->getPosition()->distanceSquared($this->location) > 1024){
			FishingContext::get()->debug("Bobber despawned: " . $owner->getName() . " moved too far away");
			$this->flagForDespawn();
			return true;
		}

		if($this->hookedEntity !== null){
			if($this->hookedEntity->isClosed() || $this->hookedEntity->isFlaggedForDespawn()){
				$this->hookedEntity = null;
			}else{
				return true;
			}
		}

		$x = (int) floor($this->location->x);
		$y = (int) floor($this->location->y);
		$z = (int) floor($this->location->z);

		$world = $this->getWorld();
		$wasInWater = $this->inWater;
		$this->inWater = $world->getBlockAt($x, $y, $z) instanceof Water || $world->getBlockAt($x, $y + 1, $z) instanceof Water;

		if($this->inWater && !$wasInWater){
			FishingContext::get()->debug("Bobber entered water at " . $x . " " . $y . " " . $z);
		}

		if($this->inWater){
			$this->blockHit = null;
			$this->isCollided = false;
			$this->onGround = false;
			$this->handleFloating($x, $y, $z);
			$this->handleFishing($x, $y, $z);
		}else{
			$this->setHasGravity(true);
		}

		return true;
	}

	private function handleFloating(int $x, int $y, int $z) : void{
		$this->setHasGravity(false);

		$topY = $y;
		while($this->getWorld()->getBlockAt($x, $topY + 1, $z) instanceof Water){
			$topY++;
		}
		$surfaceY = $topY + 0.9;

		$mY = $this->motion->y;
		$diff = $surfaceY - $this->location->y;
		if($diff > 0.05){
			$mY += 0.02;
		}elseif($diff < -0.05){
			$mY -= 0.02;
		}else{
			$mY *= 0.4;
		}

		$this->setMotion(new Vector3($this->motion->x * 0.7, $mY * 0.9, $this->motion->z * 0.7));
		$this->setForceMovementUpdate();
	}

	private function handleFishing(int $x, int $y, int $z) : void{
		$context = FishingContext::get();

		if($this->state === self::STATE_WAITING){
			if($this->waitTime === null){
				$this->resetWaitTime($context);
			}

			$decrement = 1;
			if($context->rainSpeedup && $this->isRainedOn($x, $y, $z) && mt_rand(1, 4) === 1){
				$decrement = 2;
			}
			if($context->noSkySlowdown && !$this->hasSkyAccess($x, $y, $z) && mt_rand(1, 2) === 1){
				$decrement = 0;
			}

			$this->waitTime -= $decrement;
			if($this->waitTime <= 0){
				$this->state = self::STATE_LURING;
				$this->lureTime = mt_rand(20, 80);
				$context->debug("Fish approaching, lure time " . $this->lureTime . " ticks");
			}
			return;
		}

		if($this->state === self::STATE_LURING){
			$this->spawnApproachParticles();
			if(--$this->lureTime <= 0){
				$this->bite();
			}
			return;
		}

		if($this->state === self::STATE_BITING){
			if(--$this->biteTime <= 0){
				$this->state = self::STATE_WAITING;
				$this->waitTime = null;
				$context->debug("Bite window missed, waiting again");
			}
		}
	}

	private function resetWaitTime(FishingContext $context) : void{
		$lure = $this->getEnchantLevel(true);
		$min = max(0, $context->waitMin - $lure * $context->lureReduction);
		$max = max(1, $context->waitMax - $lure * $context->lureReduction);
		$this->waitTime = mt_rand($min, $max);
		$context->debug("Wait time set to " . $this->waitTime . " ticks (Lure " . $lure . ")");
	}

	private function spawnApproachParticles() : void{
		$angle = mt_rand(0, 359) / 180 * M_PI;
		$radius = $this->lureTime * 0.1;
		$px = $this->location->x + cos($angle) * $radius;
		$pz = $this->location->z + sin($angle) * $radius;
		$this->getWorld()->addParticle(new Vector3($px, $this->location->y + 0.05, $pz), new BubbleParticle());
	}

	private function bite() : void{
		$this->state = self::STATE_BITING;
		$this->biteTime = FishingContext::get()->biteWindow;
		FishingContext::get()->debug("Bite! Reel in within " . $this->biteTime . " ticks");

		$owner = $this->getOwningEntity();
		if($owner instanceof Player){
			(new FishingBiteEvent($owner, $this))->call();
		}

		$this->getWorld()->addSound($this->location->asVector3(), new WaterSplashSound(1.0));
		for($i = 0; $i < 8; $i++){
			$ox = mt_rand(-4, 4) / 10;
			$oz = mt_rand(-4, 4) / 10;
			$this->getWorld()->addParticle(new Vector3($this->location->x + $ox, $this->location->y + 0.1, $this->location->z + $oz), new BubbleParticle());
		}
		$this->setMotion(new Vector3($this->motion->x, -0.4, $this->motion->z));
	}

	public function reelIn() : void{
		$owner = $this->getOwningEntity();

		if($this->hookedEntity !== null && !$this->hookedEntity->isFlaggedForDespawn() && $owner instanceof Player){
			$event = new FishingReelEntityEvent($owner, $this, $this->hookedEntity);
			$event->call();
			if(!$event->isCancelled()){
				$cost = $this->hookedEntity instanceof ItemEntity ? 3 : 5;
				FishingContext::get()->debug("Reeled in entity " . $this->hookedEntity->getName() . " (rod damage " . $cost . ")");
				$pull = $owner->getPosition()->subtractVector($this->hookedEntity->getPosition())->multiply(0.1);
				$this->hookedEntity->setMotion($this->hookedEntity->getMotion()->addVector($pull));
				$this->damageRod($owner, $cost);
			}
			$this->flagForDespawn();
			return;
		}

		if($this->state === self::STATE_BITING && $owner instanceof Player){
			if($this->catchLoot($owner)){
				$this->damageRod($owner, 1);
			}
			$this->flagForDespawn();
			return;
		}

		if($this->blockHit !== null && $owner instanceof Player){
			FishingContext::get()->debug("Reeled in from a solid block (rod damage 2)");
			$this->damageRod($owner, 2);
		}else{
			FishingContext::get()->debug("Reeled in with nothing caught (no rod damage)");
		}

		$this->flagForDespawn();
	}

	private function catchLoot(Player $owner) : bool{
		$context = FishingContext::get();
		$luck = $this->getEnchantLevel(false);

		$x = (int) floor($this->location->x);
		$y = (int) floor($this->location->y);
		$z = (int) floor($this->location->z);
		$biomeId = $this->getWorld()->getBiomeId($x, $y, $z);
		$openWater = $this->isOpenWater($x, $y, $z);
		$loot = $context->loot->roll($biomeId, $luck, $openWater);
		$experience = mt_rand($context->xpMin, $context->xpMax);

		$event = new FishingCatchEvent($owner, $this, $loot, $experience, $luck, $openWater);
		$event->call();
		if($event->isCancelled()){
			$context->debug("Catch cancelled by a plugin");
			return false;
		}
		$loot = $event->getLoot();
		$experience = $event->getExperience();
		$context->debug("Caught " . $loot->getCount() . "x " . $loot->getName() . " (LotS " . $luck . ", biome " . $biomeId . ", openWater " . ($openWater ? "yes" : "no") . ", xp " . $experience . ")");

		$target = $owner->getPosition();
		$dx = $target->x - $this->location->x;
		$dy = $target->y + 0.5 - $this->location->y;
		$dz = $target->z - $this->location->z;
		$distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
		$motion = new Vector3($dx * 0.1, $dy * 0.1 + sqrt($distance) * 0.08, $dz * 0.1);

		if(!$loot->isNull()){
			$this->getWorld()->dropItem($this->location->asVector3(), $loot, $motion, 5);
		}

		if($experience > 0){
			foreach($this->getWorld()->dropExperience($target, $experience) as $orb){
				$orb->setTargetPlayer($owner);
			}
		}

		return true;
	}

	private function damageRod(Player $owner, int $amount) : void{
		$inventory = $owner->getInventory();
		$hand = $inventory->getItemInHand();
		if($hand instanceof FishingRod){
			$hand->applyDamage($amount);
			$inventory->setItemInHand($hand->isNull() ? VanillaItems::AIR() : $hand);
		}
	}

	private function isOpenWater(int $x, int $y, int $z) : bool{
		for($dx = -2; $dx <= 2; $dx++){
			for($dy = -2; $dy <= 2; $dy++){
				for($dz = -2; $dz <= 2; $dz++){
					$block = $this->getWorld()->getBlockAt($x + $dx, $y + $dy, $z + $dz);
					if($block->isSolid()){
						return false;
					}
				}
			}
		}
		return true;
	}

	private function hasSkyAccess(int $x, int $y, int $z) : bool{
		$highest = $this->getWorld()->getHighestBlockAt($x, $z);
		return $highest === null || $highest <= $y;
	}

	private function isRainedOn(int $x, int $y, int $z) : bool{
		return false;
	}

	private function getEnchantLevel(bool $lure) : int{
		$owner = $this->getOwningEntity();
		if(!$owner instanceof Player){
			return 0;
		}
		$enchantment = $lure ? FishingEnchantments::lure() : FishingEnchantments::luckOfTheSea();
		return $owner->getInventory()->getItemInHand()->getEnchantmentLevel($enchantment);
	}
}
