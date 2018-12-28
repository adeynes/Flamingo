<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\event\FlamingoGenerationEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\struct\TeamOrganization;
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
    public const ERROR_PLAYING_IS_NOT_PLAYING = 'Attempted to eliminate a dead or non-existing player %player%';

    /** @var string */
    public const ERROR_DIDNT_PICK_TEAM_ORG = 'No team organization has been picked. Are there team sizes defined config.yml@team-size-optimality?';

    public const DEFAULT_TEAM_SIZE_OPTIMALITY = [2 => 0.8, 3 => 0.96, 4 => 0.88, 5 => 0.45, 6 => 0.2];

    /** @var bool */
    private $isStarted = false;

    /** @var Flamingo */
    private $plugin;

    /** @var Level */
    private $level;

    /** @var Player[] */
    private $players = [];

    /** @var Player[] */
    private $flamingos;

    /** @var TeamOrganization */
    private $teamOrganization;

    /** @var Team[] */
    private $teams = [];

    public function __construct(Flamingo $plugin, Level $level)
    {
        $this->plugin = $plugin;
        $this->level = $level;
        $this->plugin->getServer()->getPluginManager()->registerEvents($this, $this->plugin);
    }

    public function isStarted(): bool
    {
        return $this->isStarted;
    }

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

    public function getPlayer(string $name): ?Player
    {
        return $this->getPlayers()[$name] ?? null;
    }

    public function isPlayerInGame(string $name): bool
    {
        return $this->getPlayer($name) instanceof Player;
    }

    public function isPlayerPlaying(string $name): bool
    {
        return $this->isPlayerInGame($name) && $this->getPlayer($name)->isPlaying();
    }

    public function isPlayerEliminated(string $name): bool
    {
        return $this->isPlayerInGame($name) && $this->getPlayer($name)->isEliminated();
    }

    /**
     * @return Player[]
     */
    public function getFlamingos(): array
    {
        return $this->flamingos;
    }

    public function getTeamOrganization(): TeamOrganization
    {
        return $this->teamOrganization;
    }

    /**
     * @return Team[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    public function getTeam(string $name): ?Team
    {
        return $this->getTeams()[$name] ?? null;
    }

    public function addPlayers(Player ...$players): void
    {
        foreach ($players as $player) {
            $this->players[$player->getName()] = $player;
            $player->getPmPlayer()->teleport($this->getLevel()->getSafeSpawn());
        }
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            throw new \InvalidStateException(self::ERROR_GAME_IS_ALREADY_STARTED);
        }

        $this->generateTeams();
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function (int $currentTick): void {
                $this->generateFlamingos();
                (new FlamingoGenerationEvent($this))->call();
            }),
            $this->plugin->getConfig()->getNested('flamingo.delay') * 60 * 20
        );

        $side = $this->plugin->getConfig()->getNested('map.side') ?? 1800;
        $limits = [-$side/2, $side/2];
        /** @var int $minDistance */
        $minDistance = $this->plugin->getConfig()->getNested('map.minimum-spawn-distance') ?? 250;
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

        foreach ($this->getTeams() as $team) {
            for ($i = 0; $i < $minDistance; ++$i) {
                $spawn = new Vector2(rand(...$limits), rand(...$limits));
                if ($respectsMinDistance($spawn)) {
                    $spawns[$team->getName()] = $spawn;
                    continue 2; // go to the next team, don't skip to the minDistance deprecation
                }
            }
            // We haven't been able to fit the team in minDistance tries, deprecate it
            $minDistance *= 0.825;
        }

        // (Should) cancel out all damage
        $resistance = new EffectInstance(Effect::getEffect(Effect::RESISTANCE), 30 * 20, 4);
        // Regen 1 health every other tick
        $regen = new EffectInstance(Effect::getEffect(Effect::REGENERATION), 30 * 20, 4);
        // Give them resistance & regen for 30 seconds to negate the fall damage
        foreach ($this->getPlayers() as $player) {
            $player->getPmPlayer()->addEffect($resistance);
            $player->getPmPlayer()->addEffect($regen);
        }

        foreach ($spawns as $teamName => $spawn) {
            foreach ($this->getTeam($teamName)->getPlayers() as $player) {
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

    private function generateTeams(): void
    {
        $this->teamOrganization = $org = $this->findTeamOrganization();
        $playersNotDistributed = $this->getPlayers();

        $this->teams = Utils::initArrayWithClosure(
            function (int $i) use ($org, &$playersNotDistributed) {
                /** @var Player[] $randPlayers */
                $randPlayers = Utils::getRandomElems($this->getPlayers(), $org->getTeamSize(), $playersNotDistributed);
                return new Team($this->nextTeamName(), $randPlayers);
            },
            $org->getNumTeams()
        );

        $remainingPlayers = array_values($playersNotDistributed);
        $receivingTeams = Utils::getRandomElems($this->getTeams(), count($remainingPlayers));
        array_walk($receivingTeams, function (Team $team, int $i) use ($remainingPlayers) {
            /** @var Team $team */
            $team->addPlayers($remainingPlayers[$i]);
        });

        // Set the nametags
        foreach ($this->getPlayers() as $player) {
            $player->getPmPlayer()->setNameTag(
                $this->plugin->getUtils()->formatMessage(
                    'player-nametag',
                    ['name' => $player->getName(), 'team' => $player->getTeam()->getName()]
                )
            );
        }
    }

    private function generateFlamingos(): void
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
        $numTeams = $this->teamOrganization->getNumTeams();
        $flamingosPerTeam = $numTeams / $numFlamingos;
        for ($i = 1; $i <= $flamingosPerTeam; ++$i) {
            foreach ($this->getTeams() as $team) {
                $makeRandPlayerFlamingo($team);
            }
        }
        $numLeftoverFlamingos = $numTeams % $numFlamingos;
        /** @var Team[] $receivingTeams */
        $receivingTeams = Utils::getRandomElems($this->getTeams(), $numLeftoverFlamingos);
        foreach ($receivingTeams as $team) {
            $makeRandPlayerFlamingo($team);
        }
    }

    /**
     * Calculates the optimality of each team size and randomly picks one
     * (more optimized ones have a higher chance of being picked)
     *
     * @return TeamOrganization
     */
    private function findTeamOrganization(): TeamOrganization
    {
        $scores = [];
        $sizes = $this->plugin->getConfig()->get('team-size-optimality', null);
        if (is_null($sizes)) {
            $sizes = self::DEFAULT_TEAM_SIZE_OPTIMALITY;
            $this->plugin->getServer()->getLogger()->warning(self::ERROR_DIDNT_PICK_TEAM_ORG);
            $this->plugin->getServer()->getLogger()->notice('Defaulting to ' . var_export($sizes, true));
        }

        foreach ($sizes as $size => $optimality) {
            $organization = TeamOrganization::calculate($size, count($this->players));
            $numTeamsWithNumericalSup = $organization->getNumTeamsWithNumericalSup();
            // No teams with num. sup. is a 10% bonus, otherwise normalize to 0.8-1
            $numericalSupMalus = ($numTeamsWithNumericalSup === 0 ? 1.1 : (1 - 0.2 * (1 - 1/$numTeamsWithNumericalSup)));
            $score = 1 * $optimality * $numericalSupMalus;
            $scores[serialize($organization)] = $score;
        }

        $total = array_sum($scores);
        $probabilities = array_map(
            function (float $score) use ($total) { return $score / $total; },
            $scores
        );

        // Construct a structure where each numTeam occupies a certain amount of the probability space
        $probSpace = [];
        $i = 1;
        foreach ($probabilities as $organization => $probability) {
            $probSpace[$organization] = array_sum(array_slice($probabilities, 0, $i));
            ++$i;
        }

        // Normalize to 0-1
        $random = rand() / getrandmax();
        foreach ($probSpace as $organization => $start) {
            if ($random <= $start) return unserialize($organization);
        }

        throw new \InvalidStateException(self::ERROR_DIDNT_PICK_TEAM_ORG);
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
                Utils::replaceTags(self::ERROR_PLAYING_IS_NOT_PLAYING, ['player' => $name])
            );
        }

        $player->eliminate();
        unset($this->players[$name]);
    }






    private function nextTeamName(): string
    {
        return (string)rand();
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
        if (!$this->isPlayerPlaying($dead->getName())) {
            return;
        }

        $cause = $pmPlayer->getLastDamageCause();
        $deathMessageContainer = PlayerDeathEvent::deriveMessage($pmPlayer->getName(), $cause);
        $deathMessageContainer->setParameter(
            0,
            $this->plugin->getUtils()->formatMessage(
                'player-nametag',
                ['player' => $dead->getName(), 'team' => $dead->getTeam()->getName()]
            )
        );

        if ($cause instanceof EntityDamageByEntityEvent) {
            $killer = $cause->getDamager();
            if ($killer instanceof PMPlayer) {
                if ($this->isPlayerPlaying($killer->getName())) {
                    $killer = $this->getPlayer($killer->getName());
                    $deathMessageContainer->setParameter(
                        1,
                        $this->plugin->getUtils()->formatMessage(
                            'player-nametag',
                            ['player' => $killer->getName(), 'team' => $killer->getTeam()->getName()]
                        )
                    );
                }
            }
        }

        if ($dead->getTeam()->isEliminated()) {
            $this->plugin->getServer()->broadcastMessage(
                $this->plugin->getUtils()->formatMessage('team-eliminated', ['team' => $dead->getTeam()->getName()]),
                $this->getLevel()->getPlayers()
            );
        }

        $event->setDeathMessage($deathMessageContainer);
    }

}