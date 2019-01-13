<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\component\team\Team;
use adeynes\Flamingo\event\PlayerEliminationEvent;
use adeynes\Flamingo\map\Teleportable;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player as PMPlayer;

class Player implements Teleportable
{

    /** @var string */
    private $name;

    /** @var PMPlayer */
    private $pmPlayer;

    /** @var Game The Game to which the Player belongs */
    private $game;

    /**
     * @param PMPlayer $pmPlayer
     * @param Game $game
     */
    public function __construct(PMPlayer $pmPlayer, Game $game)
    {
        $this->name = $pmPlayer->getName();
        $this->pmPlayer = $pmPlayer;
        $this->game = $game;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return PMPlayer
     */
    public function getPmPlayer(): PMPlayer
    {
        return $this->pmPlayer;
    }

    /**
     * @return bool
     */
    public function isPlaying(): bool
    {
        return $this->game->getPlayer($this->getName()) === $this;
    }

    /**
     * Eliminates the player (switched to spec)
     */
    public function eliminate(): void
    {
        $this->game->addSpectator($this);
        $this->getPmPlayer()->setGamemode(PMPlayer::SPECTATOR);

        (new PlayerEliminationEvent($this, $this->game))->call();
    }



    /**
     * @param Position $position
     */
    public function teleport(Position $position): void
    {
        $this->getPmPlayer()->teleport($position);
    }

}