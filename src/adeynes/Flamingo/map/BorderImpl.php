<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use adeynes\Flamingo\Game;
use adeynes\Flamingo\Player;
use adeynes\Flamingo\utils\ConfigKeys;
use adeynes\Flamingo\utils\Utils;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;

// TODO: remove dependency on Game object, a border should be able to be used cross-game
// Hint: players can't be in multiple games at once
class BorderImpl implements Border
{

    /**
     * When a player enters the border, they will be knockbacked by distanceIntoBorder * KNOCKBACK_FACTOR
     *
     * @var float
     */
    public const KNOCKBACK_FACTOR = 2.8;

    /** @var Game */
    private $game;

    /**
     * The 'radius' of the border (distance from center to edge perpendicular to the edge)
     *
     * The border is a square even though this is called 'radius'.
     * If the radius is 750, the border will span from -750/-750 to 750/750.
     *
     * @var float
     */
    private $radius;

    /** @var float */
    private $curReductionSpeed = 0;

    /** @var int */
    private $curDamage = 0;

    /** @var int */
    private $leniency;

    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->radius = $game->getPlugin()->getConfig()->getNested(ConfigKeys::BORDER_RADIUS);
        $this->leniency = $game->getPlugin()->getConfig()->getNested(ConfigKeys::BORDER_VIOLATION_DAMAGE_LENIENCY);
    }

    /**
     * @return float
     */
    public function getRadius(): float
    {
        return $this->radius;
    }

    /**
     * @return float
     */
    public function getCurReductionSpeed(): float
    {
        return $this->curReductionSpeed;
    }

    /**
     * @return int
     */
    public function getCurDamage(): int
    {
        return $this->curDamage;
    }

    /**
     * How many blocks past the borders will players take full damage?
     *
     * If the player is past the border but below this value, reduced damage will be dealt.
     *
     * @return int
     */
    public function getLeniency(): int
    {
        return $this->leniency;
    }

    /**
     * Ticks the border (applies reduction, etc)
     *
     * @param int $curTick The game's current tick
     *
     * @internal
     */
    public function doTick(int $curTick): void
    {
        if ($curTick % 60 === 0) {
            $this->checkChanges($curTick/60);
        }

        if ($this->getCurReductionSpeed() !== 0) {
            $this->radius -= $this->getCurReductionSpeed();
            if ($this->hasReductionStopped()) { // we've gone past the radius, set it back to the stop value
                $this->radius = $this->game->getPlugin()->getConfig()->getNested(ConfigKeys::REDUCTION_STOP_RADIUS);
                $this->curReductionSpeed = 0;
            }
        }
    }

    /**
     * Check if anything (ex. reduction speed) should change starting at this minute
     *
     * @param int $curMinute
     */
    private function checkChanges(int $curMinute): void
    {
        $config = $this->game->getPlugin()->getConfig();
        if (!$this->hasReductionStopped()) {
            $speeds = $config->getNested(ConfigKeys::REDUCTION_SPEEDS);
            // Sort by key descending order (highest first)
            krsort($speeds);
            foreach ($speeds as $minute => $speed) {
                if ($curMinute >= $minute) {
                    $this->curReductionSpeed = $speed;
                    break;
                }
            }
        }

        $damageStart = $config->getNested(ConfigKeys::BORDER_VIOLATION_DAMAGE_START);
        if ($damageStart !== false && $curMinute >= $damageStart) {
            $this->curDamage = $config->getNested(ConfigKeys::BORDER_VIOLATION_DAMAGE_VALUE);
        }
    }

    /**
     * @return bool
     */
    private function hasReductionStopped(): bool
    {
        return $this->getRadius() <= $this->game->getPlugin()->getConfig()->getNested(ConfigKeys::REDUCTION_STOP_RADIUS);
    }



    /**
     * Checks if the given vector is within the border's limits
     *
     * @param Vector2 $vector
     * @return bool
     */
    public function isWithinLimits(Vector2 $vector): bool
    {
        return $vector->getX() >= -$this->getRadius() && $vector->getX() <= $this->getRadius() &&
               $vector->getY() >= -$this->getRadius() && $vector->getY() <= $this->getRadius();
    }






    /**
     * Listens to PlayerMoveEvent to detect border collision/penetration
     *
     * @param PlayerMoveEvent $event
     *
     * @priority LOW
     */
    public function onMove(PlayerMoveEvent $event): void
    {
        $player = $this->game->getPlayer($event->getPlayer()->getName());
        if (!$player instanceof Player || !$player->isPlaying()) {
            return;
        }

        $pmPlayer = $player->getPmPlayer();
        $playerVec2 = Utils::vec3ToVec2($pmPlayer);
        if ($this->isWithinLimits($playerVec2)) {
            return;
        }

        // Through which vector did the player enter the border?
        $penetrationVector = Utils::calculateVectorSquareIntersection($playerVec2, $this->getRadius());
        // How far into the border the player is
        $diffVector = $playerVec2->subtract($penetrationVector);

        // We're not using Living::knockBack() because that thing looks like a dinosaur
        // TODO: fine-tune factors
        // Go the opposite way that we went into the border & go up a bit
        $knockBackMotion = (new Vector3(-$diffVector->getX(), 0.5, -$diffVector->getY()))->multiply(self::KNOCKBACK_FACTOR);
        // Divide the current motion by 3 and add the knockback motion
        $pmPlayer->setMotion($pmPlayer->getMotion()->divide(3)->add($knockBackMotion));

        if ($damage = $this->getCurDamage()) {
            $pmPlayer->setHealth($pmPlayer->getHealth() - $damage);
        }
    }

}