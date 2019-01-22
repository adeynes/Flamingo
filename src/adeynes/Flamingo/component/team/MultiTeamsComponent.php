<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\component\Component;
use adeynes\Flamingo\event\GamePreStartEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\event\PlayerEliminationEvent;
use adeynes\Flamingo\Game;
use adeynes\Flamingo\Player;
use adeynes\Flamingo\utils\ConfigKeys;
use adeynes\Flamingo\utils\TeamConfig;
use adeynes\Flamingo\utils\Utils;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector2;

final class MultiTeamsComponent extends TeamsComponent
{

    /** @var string */
    public const ERROR_DIDNT_PICK_TEAM_CONFIG = 'No team config has been picked. Are there team sizes defined config#team-size-optimality?';

    /**
     * The default optimality values that will be used if none are specified in the config
     *
     * @var float[]
     */
    private const DEFAULT_TEAM_SIZE_OPTIMALITY = [2 => 0.8, 3 => 0.96, 4 => 0.88, 5 => 0.45, 6 => 0.2];

    /** @var int */
    private const DEFAULT_MINIMUM_SPAWN_DISTANCE = 250;

    /** @var TeamConfig|null */
    private $teamConfig;


    /**
     * @param Game $game
     * @param TeamConfig|null $teamConfig
     */
    public function __construct(Game $game, ?TeamConfig $teamConfig)
    {
        $this->teamConfig = $teamConfig;
        parent::__construct($game);
    }



    public function getTeamConfig(): ?TeamConfig
    {
        return $this->teamConfig;
    }



    /**
     * Generates teams based on an optimized (yet randomized) algorithm
     *
     * @see TeamManager::pickTeamConfig()
     * @see TeamManager::nextTeamName()
     */
    private function generateTeams(): void
    {
        $this->teamConfig = $config = $this->teamConfig ?? $this->pickTeamConfig();
        // Keep a reference so we don't have to type $this->game->getPlayers() every time
        $players = &$this->game->getPlayers();
        // This is a copy, not a reference
        $playersNotDistributed = $players;

        $this->teams = Utils::initArrayWithClosure(
            function (int $i) use ($config, &$playersNotDistributed) {
                /** @var Player[] $randPlayers */
                $randPlayers = Utils::getRandomElems($this->game->getPlayers(), $config->getTeamSize(), $playersNotDistributed);
                return new Team($this->nextTeamName(), 0, $randPlayers);
            },
            $config->getNumTeams()
        );

        // Get rid of assoc keys (player's name), we want them numerically indexed
        $remainingPlayers = array_values($playersNotDistributed);
        $receivingTeams = Utils::getRandomElems($this->getTeams(), count($remainingPlayers));
        array_walk($receivingTeams, function (Team $team, int $i) use ($remainingPlayers) {
            /** @var Team $team */
            $team->addPlayers($remainingPlayers[$i]);
        });

        $this->playingTeams = $this->teams;
    }

    /**
     * Calculates the score of each team size and randomly picks one (ones with + score are more likely to be picked)
     *
     * The score is OPTIMALITY * NUMERICAL_SUP_MALUS.
     * OPTIMALITY is defined in the config#team-size-optimality.
     * NUMERICAL_SUP_MALUS varies in [0.8, 1.08]. It is:
     * { 1.08                                           if NUM_TEAMS_WITH_NUMERICAL_SUP = 0
     * { 1 - 0.2(1 - 1/NUM_TEAMS_WITH_NUMERICAL_SUP)    otherwise
     * NUM_TEAMS_WITH_NUMERICAL_SUP is the number of teams with a numerical superiority. The less, the better.
     *
     * @return TeamConfig
     */
    private function pickTeamConfig(): TeamConfig
    {
        $scores = [];
        $sizes = $this->game->getPlugin()->getConfig()->get(ConfigKeys::TEAM_SIZE_OPTIMALITY, null);
        if (is_null($sizes)) {
            $sizes = self::DEFAULT_TEAM_SIZE_OPTIMALITY;
            $logger = $this->game->getPlugin()->getServer()->getLogger();
            $logger->warning(self::ERROR_DIDNT_PICK_TEAM_CONFIG);
            $logger->notice('Defaulting to ' . var_export($sizes, true));
        }

        foreach ($sizes as $size => $optimality) {
            $config = TeamConfig::calculate($size, count($this->game->getPlayers()));
            $numTeamsWithNumericalSup = $config->getNumTeamsWithNumericalSup();
            // No teams with num. sup. is an 8% bonus, otherwise normalize to 0.8-1
            $numericalSupScore = ($numTeamsWithNumericalSup === 0 ? 1.08 : (1 - 0.2 * (1 - 1/$numTeamsWithNumericalSup)));
            $score = $optimality * $numericalSupScore;
            $scores[serialize($config)] = $score;
        }

        $total = array_sum($scores);
        $probabilities = array_map(
            function (float $score) use ($total) { return $score / $total; },
            $scores
        );

        // Construct a structure where each numTeam occupies a certain amount of the probability space
        $probSpace = [];
        $i = 1;
        foreach ($probabilities as $config => $probability) {
            $probSpace[$config] = array_sum(array_slice($probabilities, 0, $i));
            ++$i;
        }

        // Normalize to 0-1
        $random = rand() / getrandmax();
        foreach ($probSpace as $config => $start) {
            if ($random <= $start) return unserialize($config);
        }

        throw new \InvalidStateException(self::ERROR_DIDNT_PICK_TEAM_CONFIG);
    }



    /**
     * Generates the next team's name
     *
     * TODO: actually generate something sensical
     *
     * @return string
     */
    private function nextTeamName(): string
    {
        return (string)rand();
    }






    /**
     * @param GamePreStartEvent $event
     */
    public function onGamePreStart(GamePreStartEvent $event): void
    {
        if ($event->getGame() !== $this->game) {
            return;
        }

        // TODO: ability to plugin into the team generator (TeamGenerator interface?) (or just closure)
        $this->generateTeams();

        $minDistance = $this->game->getPlugin()->getConfig()->getNested(ConfigKeys::TEAMS_MINIMUM_SPAWN_DISTANCE)
                       ?? self::DEFAULT_MINIMUM_SPAWN_DISTANCE;

        $this->game->getMap()->getSpawnGenerator()->generateSpawns(
            count($this->getTeams()),
            $minDistance,
            function (array $spawns): void {
                /** @var Position[] $spawns */

                $count = 0;
                foreach ($this->getTeams() as $team) {
                    $team->teleport($spawns[$count]);
                    ++$count;
                }
            }
        );
    }

    /**
     * @param PlayerEliminationEvent $event
     */
    public function onPlayerElimination(PlayerEliminationEvent $event): void
    {
        // TODO: keep a list of teams indexed by player?
        foreach ($this->getTeams() as $team) {
            if ($team->getPlayer($event->getPlayer()->getName()) === null) {
                continue;
            }
            if ($team->isEliminated()) {
                unset($this->playingTeams[$team->getName()]);
                // TODO: elimination message
            }
        }

        $this->checkWinCondition();
    }

}