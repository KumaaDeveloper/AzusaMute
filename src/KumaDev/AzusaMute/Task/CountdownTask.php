<?php

namespace KumaDev\AzusaMute\Task;

use pocketmine\scheduler\Task;
use KumaDev\AzusaMute\Main;

class CountdownTask extends Task {

    private $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $data = $this->plugin->getData();
        $currentTime = time();

        foreach ($data->getAll() as $playerName => $info) {
            if ($playerName === 'muteall') continue;
            if ($info['time'] <= $currentTime) {
                $data->remove($playerName);
                $data->save();
                $player = $this->plugin->getServer()->getPlayerExact($playerName);
                if ($player) {
                    $player->sendMessage($this->plugin->getPluginConfig()['messages']['mute_ended']);
                    $this->plugin->playSound($player, "random.levelup");
                }
            }
        }
    }
}