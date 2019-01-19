<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use adeynes\Flamingo\event\GameStartEvent;
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

    /**
     * How far into the border can the player get before they get knockbacked? (Can't be 0 or they won't be knockbacked)
     *
     * @var float
     */
    public const LENIENCY = 3;

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

    private $isBorderEnforced = false;

    /** @var float */
    private $curReductionSpeed = 0;

    /** @var int */
    private $curDamage = 0;

    public function __construct(Game $game)
    {
        $this->game = $game;
        $this->radius = $game->getPlugin()->getConfig()->getNested(ConfigKeys::BORDER_RADIUS);
    }

    /**
     * @return float
     */
    public function getRadius(): float
    {
        return $this->radius;
    }

    /**
     * @return bool
     */
    public function isBorderEnforced(): bool
    {
        return $this->isBorderEnforced;
    }

    /**
     * @param bool $value
     */
    public function setBorderEnforced(bool $value = true): void
    {
        $this->isBorderEnforced = $value;
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
     * Checks if the given vector is within the border's limits. Doesn't take into account the LENIENCY
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
     * @param GameStartEvent $event
     */
    public function onGameStart(GameStartEvent $event): void
    {
        $this->setBorderEnforced(true);
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
        if (!$this->isBorderEnforced()) {
            return;
        }

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
        $distance = $playerVec2->distance($penetrationVector);

        if ($distance < self::LENIENCY) {
            return;
        }

        // How far into the border the player is
        $diffVector = $playerVec2->subtract($penetrationVector);

        // We're not using Living::knockBack() because we want more fine control
        // This is pretty much a copy though
        /** @see Living::knockBack() */
        // TODO: fine-tune factors a bit more (though this is pretty much fine)
        $base = 0.32;
        $x = -$diffVector->getX();
        $y = $base**2;
        $z = -$diffVector->getY();
        $f = 1/$distance;

        $knockBackMotion = (new Vector3($x*$f*$base, $y, $z*$f*$base))
                           ->multiply(self::KNOCKBACK_FACTOR);
        var_dump($knockBackMotion);
        $pmPlayer->setMotion($pmPlayer->getMotion()->divide(3)->add($knockBackMotion));
        var_dump($pmPlayer->getMotion());


        if ($damage = $this->getCurDamage()) {
            $pmPlayer->setHealth($pmPlayer->getHealth() - $damage);
        }
    }

}