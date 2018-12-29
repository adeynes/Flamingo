<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\struct\TeamOrganization;
use adeynes\Flamingo\utils\ConfigKeys;
use adeynes\Flamingo\utils\Utils;

/**
 * A class to manage the teams
 *
 * This was extracted from the Game class as there are many heavy methods (ex. the team organization algorithm).
 */
class TeamManager
{

    /** @var string */
    public const ERROR_DIDNT_PICK_TEAM_ORG = 'No team organization has been picked. Are there team sizes defined config#team-size-optimality?';

    /**
     * The default optimality values that will be used if none are specified in the config
     *
     * @var float[]
     */
    public const DEFAULT_TEAM_SIZE_OPTIMALITY = [2 => 0.8, 3 => 0.96, 4 => 0.88, 5 => 0.45, 6 => 0.2];

    /** @var Game */
    private $game;

    /** @var TeamOrganization */
    private $organization;

    /** @var Team[] */
    private $teams;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    /**
     * @return TeamOrganization
     */
    public function getOrganization(): TeamOrganization
    {
        return $this->organization;
    }

    /**
     * @return Team[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    /**
     * @param string $name
     * @return Team|null
     */
    public function getTeam(string $name): ?Team
    {
        return $this->getTeams()[$name] ?? null;
    }

    /**
     * Generates teams based on an optimized (yet randomized) algorithm
     *
     * @see TeamManager::findTeamOrganization()
     * @see TeamManager::nextTeamName()
     *
     * @internal
     */
    public function generateTeams(): void
    {
        $this->organization = $org = $this->findTeamOrganization();
        // Keep a reference so we don't have to type $this->game->getPlayers() every time
        $players = &$this->game->getPlayers();
        // This is a copy, not a reference
        $playersNotDistributed = $players;

        $this->teams = Utils::initArrayWithClosure(
            function (int $i) use ($org, &$playersNotDistributed) {
                /** @var Player[] $randPlayers */
                $randPlayers = Utils::getRandomElems($this->game->getPlayers(), $org->getTeamSize(), $playersNotDistributed);
                return new Team($this->nextTeamName(), $randPlayers);
            },
            $org->getNumTeams()
        );

        // Get rid of assoc keys (player's name), we want them numerically indexed
        $remainingPlayers = array_values($playersNotDistributed);
        $receivingTeams = Utils::getRandomElems($this->getTeams(), count($remainingPlayers));
        array_walk($receivingTeams, function (Team $team, int $i) use ($remainingPlayers) {
            /** @var Team $team */
            $team->addPlayers($remainingPlayers[$i]);
        });
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
     * @return TeamOrganization
     */
    private function findTeamOrganization(): TeamOrganization
    {
        $scores = [];
        $sizes = $this->game->getPlugin()->getConfig()->get(ConfigKeys::TEAM_SIZE_OPTIMALITY, null);
        if (is_null($sizes)) {
            $sizes = self::DEFAULT_TEAM_SIZE_OPTIMALITY;
            $logger = $this->game->getPlugin()->getServer()->getLogger();
            $logger->warning(self::ERROR_DIDNT_PICK_TEAM_ORG);
            $logger->notice('Defaulting to ' . var_export($sizes, true));
        }

        foreach ($sizes as $size => $optimality) {
            $organization = TeamOrganization::calculate($size, count($this->game->getPlayers()));
            $numTeamsWithNumericalSup = $organization->getNumTeamsWithNumericalSup();
            // No teams with num. sup. is an 8% bonus, otherwise normalize to 0.8-1
            $numericalSupScore = ($numTeamsWithNumericalSup === 0 ? 1.08 : (1 - 0.2 * (1 - 1/$numTeamsWithNumericalSup)));
            $score = $optimality * $numericalSupScore;
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

}