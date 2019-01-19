<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\event\GamePreStartEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\event\PlayerAdditionEvent;
use adeynes\Flamingo\event\PlayerEliminationEvent;
use adeynes\Flamingo\Game;
use adeynes\Flamingo\utils\ConfigKeys;
use adeynes\Flamingo\utils\LangKeys;
use adeynes\Flamingo\utils\TeamConfig;
use adeynes\Flamingo\utils\Utils;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\Position;

final class SoloTeamsComponent extends TeamsComponent
{

    /** @var int */
    private const DEFAULT_MINIMUM_SPAWN_DISTANCE = 150;



    public function generateTeams(): void
    {
        foreach ($this->game->getPlayers() as $player) {
            $this->teams[$player->getName()] = new Team($player->getName(), 1, [$player]);

            $player->getPmPlayer()->setNameTag(
                Utils::getInstance()->formatMessage(
                    LangKeys::PLAYER_NAMETAG,
                    [
                        'team-color' => $this->game->getPlugin()->getLang()->getNested(LangKeys::TEAM_DEFAULT_COLOR),
                        'player' => $player->getName()
                    ]
                )
            );
        }

        $this->playingTeams = $this->teams;
    }






    /**
     * @param GamePreStartEvent $event
     */
    public function onGamePreStart(GamePreStartEvent $event): void
    {
        if ($event->getGame() !== $this->game) {
            return;
        }

        $this->generateTeams();

        $minDistance = $this->game->getPlugin()->getConfig()->getNested(ConfigKeys::SOLO_MINIMUM_SPAWN_DISTANCE)
            ?? self::DEFAULT_MINIMUM_SPAWN_DISTANCE;

        $this->game->getMap()->getSpawnGenerator()->generateSpawns(
            count($this->getTeams()),
            $minDistance,
            function (array $spawns): void {
                /** @var Position[] $spawns */

                $count = 0;
                foreach ($this->getTeams() as $team) {
                    $team->getPlayer()->getPmPlayer()->addEffect(Utils::getInvincibilityResistance());
                    $team->getPlayer()->teleport($spawns[$count]);
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
        unset($this->playingTeams[$event->getPlayer()->getName()]);

        $winner = $this->checkWinCondition();
        if ($winner instanceof Team) {
            $this->game->onWin($winner);
        }
    }

}