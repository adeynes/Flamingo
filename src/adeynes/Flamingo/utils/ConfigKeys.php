<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

interface ConfigKeys
{

    /** @var string */
    public const VERSION = 'version';

    /** @var string */
    public const DATABASE = 'database';

    /** @var string */
    public const TEAM_SIZE_OPTIMALITY = 'team.size-optimality';

    /** @var string */
    public const FLAMINGO_REVELATION_DELAY = 'flamingo.revelation-delay';

    /** @var string */
    public const FLAMINGO_PROPORTION = 'flamingo.proportion';

    /** @var string */
    public const BORDER_RADIUS = 'map.border.radius';

    /**
     * This will be an array of speeds [minute => blocks/s]
     *
     * @var string
     */
    public const REDUCTION_SPEEDS = 'map.border.reduction.speeds';

    /** @var string */
    public const REDUCTION_STOP_RADIUS = 'map.border.reduction.stops-at';

    /** @var string */
    public const PUSH_PLAYERS_AWAY_FROM_BORDER = 'map.border.push-away';

    /** @var string */
    public const BORDER_VIOLATION_DAMAGE_START = 'map.border.deal-damage.start';

    /** @var string */
    public const BORDER_VIOLATION_DAMAGE_VALUE = 'map.border.deal-damage.damage';

    /** @var string */
    public const TEAMS_MINIMUM_SPAWN_DISTANCE = 'map.minimum-spawn-distance.teams';

    /** @var string */
    public const SOLO_MINIMUM_SPAWN_DISTANCE = 'map.minimum-spawn-distance.solo';

}