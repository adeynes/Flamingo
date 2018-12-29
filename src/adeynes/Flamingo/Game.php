<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\event\FlamingoRevelationEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\utils\ConfigKeys;
use adeynes\Flamingo\utils\LangKeys;
use adeynes\Flamingo\utils\Utils;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\level\Level;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\Player as PMPlayer;
use pocketmine\scheduler\ClosureTask;

final class Game implements Listener
{

    /** @var string */
    public const ERROR_GAME_IS_ALREADY_STARTED = 'Attempted to start a game that was already started';

    /** @var string */
    public const ERROR_PLAYER_IS_NOT_PLAYING = 'Attempted to eliminate a dead or non-existing player %player%';

    /**
     * The factor by which the minimum spawn distance will be multiplied if a team cannot be fitted
     *
     * @var float
     */
    private const SPAWN_DISTANCE_DEPRECATION_FACTOR = 0.825;

    /** @var Flamingo */
    private $plugin;

    /** @var bool */
    private $isStarted = false;

    /** @var TeamManager */
    private $teamManager;

    /** @var Level */
    private $level;

    /** @var Player[] */
    private $players = [];

    /** @var Player[] */
    private $flamingos;

    public function __construct(Flamingo $plugin, Level $level)
    {
        $this->plugin = $plugin;
        $this->teamManager = new TeamManager($this);
        $this->level = $level;
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
    }

    /**
     * @return Flamingo
     */
    public function getPlugin(): Flamingo
    {
        return $this->plugin;
    }

    /**
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * @return TeamManager
     */
    public function getTeamManager(): TeamManager
    {
        return $this->teamManager;
    }

    /**
     * @return Level
     */
    public function getLevel(): Level
    {
        return $this->level;
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @param string $name
     * @return Player|null
     */
    public function getPlayer(string $name): ?Player
    {
        return $this->getPlayers()[$name] ?? null;
    }

    /**
     * @param Player ...$players
     */
    public function addPlayers(Player ...$players): void
    {
        foreach ($players as $player) {
            $this->players[$player->getName()] = $player;
            $player->getPmPlayer()->teleport($this->getLevel()->getSafeSpawn());
        }
    }

    /**
     * @return Player[]
     */
    public function getFlamingos(): array
    {
        return $this->flamingos;
    }

    /**
     * Starts the game
     *
     * This generates teams, flamingos, and teleports the teams to a randomized spawnpoint of the map.
     */
    public function start(): void
    {
        if ($this->isStarted()) {
            throw new \InvalidStateException(self::ERROR_GAME_IS_ALREADY_STARTED);
        }

        $this->getTeamManager()->generateTeams();

        // Set the player's nametags (they will have their team in it)
        $this->doToAllPlayers(function (Player $player): void {
            $player->getPmPlayer()->setNameTag($player->getNameTag());
        });

        // Flamingos are picked at the start of the game, but revealed later (config#flamingo.revelation-delay)
        $this->pickFlamingos();
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function (int $currentTick): void {
                $this->revealFlamingos();
                (new FlamingoRevelationEvent($this))->call();
            }),
            $this->plugin->getConfig()->getNested(ConfigKeys::FLAMINGO_REVELATION_DELAY) * 60 * 20
        );

        // 99% of the time, at least 32 teams can be fit with the 1800/250 defaults
        $side = $this->plugin->getConfig()->getNested(ConfigKeys::MAP_SIDE) ?? 1800;
        $limits = [-$side/2, $side/2];
        /** @var int $minDistance */
        $minDistance = $this->plugin->getConfig()->getNested(ConfigKeys::MINIMUM_SPAWN_DISTANCE) ?? 250;
        /** @var Vector2[] $spawns */
        $spawns = [];

        $respectsMinDistance = function (Vector2 $vector) use ($minDistance, $spawns): bool {
            foreach ($spawns as $spawn) {
                if ($vector->distance($spawn) < $minDistance) {
                    return false;
                }
            }
            return true;
        };

        foreach ($this->getTeamManager()->getTeams() as $team) {
            for ($i = 0; $i < $minDistance; ++$i) {
                $spawn = new Vector2(rand(...$limits), rand(...$limits));
                if ($respectsMinDistance($spawn)) {
                    $spawns[$team->getName()] = $spawn;
                    continue 2; // go to the next team, don't skip to the minDistance deprecation
                }
            }
            // We haven't been able to fit the team in minDistance tries, deprecate it
            $minDistance *= self::SPAWN_DISTANCE_DEPRECATION_FACTOR;
        }

        // (Should) cancel out all damage
        $resistance = new EffectInstance(Effect::getEffect(Effect::RESISTANCE), 30 * 20, 4);
        // Regen 1 health every other tick
        $regen = new EffectInstance(Effect::getEffect(Effect::REGENERATION), 30 * 20, 4);
        // Give all players resistance & regen for 30 seconds to negate the fall damage
        $this->doToAllPlayers(function (Player $player) use ($resistance, $regen): void {
            $player->getPmPlayer()->addEffect($resistance);
            $player->getPmPlayer()->addEffect($regen);
        });

        foreach ($spawns as $teamName => $spawn) {
            foreach ($this->getTeamManager()->getTeam($teamName)->getPlayers() as $player) {
                // Spawn at a random position 15 blocks away from the spawn
                $randX = rand(-15, 15);
                $randZ = sqrt(15**2 - $randX**2);
                // Randomize randZ sign (else it would always be positive)
                if (rand(0, 1)) {
                    $randZ *= -1;
                }
                $x = $spawn->getFloorX() + $randX;
                // Vector2->getY() is our z value
                $z = $spawn->getFloorY() + $randZ;
                // They spawn 30 blocks up
                $y = $this->getLevel()->getHighestBlockAt($x, $z) + 30;
                $player->getPmPlayer()->teleport(new Vector3($x, $y, $z));
            }
        }

        $this->isStarted = true;
        (new GameStartEvent($this))->call();
    }

    /**
     * Picks the flamingos
     *
     * Flamingos are picked at the beginning of the game, but revealed after a specified amount of time
     * (defined in config#flamingo.delay)
     */
    private function pickFlamingos(): void
    {
        $makeRandPlayerFlamingo = function (Team $team): void {
            do {
                /** @var Player $randPlayer */
                $randPlayer = Utils::getRandomElem($team->getPlayers());
                if (!$isFlamingo = $randPlayer->isFlamingo()) {
                    $randPlayer->setFlamingo(true);
                    $this->flamingos[$randPlayer->getName()] = $randPlayer;
                }
            } while ($isFlamingo);
        };

        $numFlamingos = round($this->plugin->getConfig()->getNested('flamingo.proportion') * count($this->getPlayers()));
        $numTeams = $this->getTeamManager()->getOrganization()->getNumTeams();
        $flamingosPerTeam = $numTeams / $numFlamingos;
        $teams = $this->getTeamManager()->getTeams();
        for ($i = 1; $i <= $flamingosPerTeam; ++$i) {
            foreach ($teams as $team) {
                $makeRandPlayerFlamingo($team);
            }
        }
        $numLeftoverFlamingos = $numTeams % $numFlamingos;
        /** @var Team[] $receivingTeams */
        $receivingTeams = Utils::getRandomElems($teams, $numLeftoverFlamingos);
        foreach ($receivingTeams as $team) {
            $makeRandPlayerFlamingo($team);
        }
    }

    /**
     * Reveals to those concerned if they are a flamingo
     */
    public function revealFlamingos(): void
    {

    }



    /**
     * @param string $name
     * @throws \InvalidStateException If the player is dead or non-existing
     */
    public function eliminatePlayer(string $name): void
    {
        $player = $this->getPlayer($name);
        if ($player === null) {
            throw new \InvalidStateException(
                Utils::replaceTags(self::ERROR_PLAYER_IS_NOT_PLAYING, ['player' => $name])
            );
        }

        $player->eliminate();
        unset($this->players[$name]);
    }






    /**
     * @param PlayerDeathEvent $event
     * @priority HIGHEST
     */
    public function onDeath(PlayerDeathEvent $event): void
    {
        $dead = $this->getPlayer($event->getPlayer()->getName());
        $dead->eliminate();
        $pmPlayer = $dead->getPmPlayer();
        if ($this->getPlayer($dead->getName()) === null || $dead->isEliminated()) {
            return;
        }

        $cause = $pmPlayer->getLastDamageCause();
        $deathMessageContainer = PlayerDeathEvent::deriveMessage($pmPlayer->getName(), $cause);
        $deathMessageContainer->setParameter(0, $dead->getNameTag());

        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();
            if ($killer instanceof PMPlayer) {
                if ($this->getPlayer($killer->getName()) instanceof Player) {
                    $killer = $this->getPlayer($killer->getName());
                    $deathMessageContainer->setParameter(1, $killer->getNameTag());
                }
            }
        }

        if ($dead->getTeam()->isEliminated()) {
            $this->plugin->getServer()->broadcastMessage(
                Utils::getInstance()->formatMessage(LangKeys::TEAM_ELIMINATED, ['team' => $dead->getTeam()->getName()]),
                $this->getLevel()->getPlayers()
            );
        }

        $event->setDeathMessage($deathMessageContainer);
    }






    /**
     * Passed each player to a specified closure
     *
     * @param \Closure $closure
     */
    public function doToAllPlayers(\Closure $closure): void
    {
        foreach ($this->getPlayers() as $player) {
            $closure($player);
        }
    }

}