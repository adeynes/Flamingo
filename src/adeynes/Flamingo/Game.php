<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\struct\TeamOrganization;
use adeynes\Flamingo\utils\Utils;
use pocketmine\scheduler\ClosureTask;

final class Game
{

    /** @var string */
    public const ATTEMPTED_TO_START_ALREADY_STARTED_GAME = 'Attempted to start a game that was already started';

    /** @var int[] */
    private const TEAM_SIZE_OPTIMALITY = [2 => 0.85, 3 => 0.98, 4 => 0.8, 5 => 0.45, 6 => 0.2];

    /**
     * The number of flamingo teams will be 1 + floor(FLAMINGO_RATIO * numRegularTeams)
     * @var float
     */
    private const FLAMINGO_RATIO = 1/6;

    /** @var Flamingo */
    private $plugin;

    /** @var bool */
    private $isStarted = false;

    /** @var ?int */
    private $teamSize;

    /** @var TeamOrganization */
    private $teamOrganization;

    /** @var Team[] */
    private $regularTeams = [];

    /** @var Team[] */
    private $flamingoTeams = [];

    /** @var Player[] */
    private $players;

    public function __construct(Flamingo $plugin, int $teamSize = null)
    {
        $this->plugin = $plugin;
        $this->teamSize = $teamSize;
    }

    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    public function getTeamOrganization(): TeamOrganization
    {
        return $this->teamOrganization;
    }

    /**
     * @return Team[]
     */
    public function getRegularTeams(): array
    {
        return $this->regularTeams;
    }

    public function addPlayers(Player ...$players): void
    {
        foreach ($players as $player) {
            $this->players[$player->getName()] = $player;
        }
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            throw new \InvalidStateException(self::ATTEMPTED_TO_START_ALREADY_STARTED_GAME);
        }

        $this->generateRegularTeams();
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new ClosureTask(function (int $currentTick): void {
                $this->generateFlamingoTeams();
            }),
            $this->plugin->getConfig()->get('flamingo-delay') * 60 * 20
        );

        $this->isStarted = true;
    }

    /**
     * Generates the regular teams (flamingo are generated later)
     * @throws \InvalidStateException If the actual remaining players after equal player distribution
     * does not === the theoretical remainder in the TeamOrganization
     */
    private function generateRegularTeams(): void
    {
        $this->teamOrganization = $org = $this->teamSize === null ? $this->findTeamSize() :
                                  TeamOrganization::calculate($this->teamSize, count($this->players));
        $playersNotDistributed = $this->players;

        $this->regularTeams = Utils::initArrayWithClosure(
            function (int $i) use ($org, &$playersNotDistributed) {
                $randPlayers = Utils::getRandomElems($this->players, $org->getTeamSize(), $playersNotDistributed);
                return new Team(Team::TEAM_TYPE_REGULAR, $randPlayers);
            },
            $org->getNumTeams()
        );

        $numExtraPlayers = $org->getNumTeamsWithNumericalSup();
        // The actual remaining players need to === the theoretical remaining players
        if (count($playersNotDistributed) !== $numExtraPlayers) {
            throw new \InvalidStateException(
                'Actual remaining players !== theoretical remainder in Game::generateRegularTeams' . PHP_EOL .
                'theo. remainder: ' . $numExtraPlayers . PHP_EOL .
                'original players: ' . var_export($this->players) . PHP_EOL .
                'players not dist.: ' . var_export($playersNotDistributed) . PHP_EOL .
                'reg. teams: ' . var_export($this->getRegularTeams())
            );
        };

        $remainingPlayers = array_values($playersNotDistributed);
        /** @var Team[] $receivingTeams */
        $receivingTeams = Utils::getRandomElems($this->getRegularTeams(), $numExtraPlayers);
        array_walk($receivingTeams, function ($team, $i) use ($remainingPlayers) {
            /** @var Team $team */
            $team->addPlayers($remainingPlayers[$i]);
        });
    }

    private function generateFlamingoTeams(): void
    {
        $teamSize = $this->getTeamOrganization()->getTeamSize();
        $numFlamingoTeams = 1 + floor(1/6 * $this->getTeamOrganization()->getNumTeams());
        $flamingoPlayers = Utils::getRandomElems($this->players, $numFlamingoTeams * $teamSize);
        $chunks = array_chunk($flamingoPlayers, $teamSize);
        $this->flamingoTeams = Utils::initArrayWithClosure(
            function (int $i) use ($chunks) {
                return new Team(Team::TEAM_TYPE_FLAMINGO, $chunks[$i]);
            },
            $numFlamingoTeams
        );
    }

    /**
     * Calculates the optimality of each team size and randomly picks one
     * (more optimized ones have a higher chance of being picked)
     * @return TeamOrganization
     * @throws \InvalidStateException If the method reaches a state where it has not decided on a team size after
     * having iterated through all the possibilities (should never happen, prob. space is 0-1 and so is random)
     */
    private function findTeamSize(): TeamOrganization
    {
        $scores = [];
        foreach (self::TEAM_SIZE_OPTIMALITY as $possibleSize => $optimality) {
            $organization = TeamOrganization::calculate($possibleSize, count($this->players));
            $numTeamsWithNumericalSup = $organization->getNumTeamsWithNumericalSup();
            // No teams with num. sup. is a 10% bonus, otherwise normalize to 0.8-1
            $numericalSupMalus = ($numTeamsWithNumericalSup === 0 ? 1.1 : (1 - 0.2 * (1 - 1/$numTeamsWithNumericalSup)));
            $score = 1 * $optimality * $numericalSupMalus;
            $scores[serialize($organization)] = $score;
        }

        $total = array_sum($scores);
        $probabilities = array_map(
            function ($score) use ($total) { return $score / $total; },
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

        throw new \InvalidStateException(
            'Reached end of Game::findTeamSize() without having decided on a size' . PHP_EOL .
            'count: ' . count($this->players) . PHP_EOL .
            'scores: ' . var_export($scores, true) . PHP_EOL .
            'probabilities: ' . var_export($probabilities, true) . PHP_EOL .
            'prob. space: ' . var_export($probSpace, true) . PHP_EOL .
            'random: ' . $random
        );
    }

}