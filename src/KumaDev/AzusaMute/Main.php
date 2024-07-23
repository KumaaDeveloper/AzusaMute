<?php

namespace KumaDev\AzusaMute;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\TextFormat as TF;
use KumaDev\AzusaMute\Task\CountdownTask;
use KumaDev\AzusaMute\Time\TimeManager;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;

class Main extends PluginBase implements Listener {

    private $data;
    private $config;
    private $timeManager;
    private $lastMessageTime = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig()->getAll();
        $this->data = new Config($this->getDataFolder() . "data.yml", Config::YAML);
        $this->timeManager = new TimeManager(); // No parameters needed
        $this->getScheduler()->scheduleRepeatingTask(new CountdownTask($this), 40); // Update every 2 seconds (40 ticks)
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch($command->getName()) {
            case "mute":
                if (count($args) < 2) {
                    return false;
                }
                $playerName = $args[0];
                $time = (int)$args[1];
                $this->mutePlayer($sender, $playerName, $time);
                return true;

            case "unmute":
                if (count($args) < 1) {
                    return false;
                }
                $playerName = $args[0];
                $this->unmutePlayer($sender, $playerName);
                return true;

            case "muteall":
                $this->muteAllPlayers($sender);
                return true;

            case "unmuteall":
                $this->unmuteAllPlayers($sender);
                return true;

            case "mutecheck":
                if (count($args) < 1) {
                    return false;
                }
                $playerName = $args[0];
                $this->checkMuteStatus($sender, $playerName);
                return true;

            default:
                return false;
        }
    }

    private function isPlayerOp(Player $player): bool {
        return $this->config['allow-op'] && $this->getServer()->isOp($player->getName());
    }

    private function mutePlayer(CommandSender $sender, string $playerName, int $time): void {
        $player = $this->getServer()->getPlayerExact($playerName);
        if ($player) {
            if ($this->isPlayerOp($player)) {
                $sender->sendMessage($this->config['messages']['op_mute']);
                $this->playSound($player, "note.didgeridoo");
                return;
            }
            $playerName = $player->getName();
        }

        $data = $this->data->get($playerName);
        if ($data) {
            $sender->sendMessage($this->config['messages']['already_muted']);
            if ($sender instanceof Player) {
                $this->playSound($sender, "note.didgeridoo");
            }
            return;
        }

        $punisher = $sender instanceof Player ? $sender->getName() : "Server";
        $this->data->set($playerName, [
            'time' => time() + $time,
            'punisher' => $punisher
        ]);
        $this->data->save();

        $formattedTime = $this->timeManager->formatTime($time);
        $this->getServer()->broadcastMessage(str_replace(['{player}', '{time}'], [$playerName, $formattedTime], $this->config['messages']['broadcast_mute']));
        $this->broadcastSound("note.bass");

        if ($player) {
            $player->sendMessage(str_replace(['{time}', '{punisher}'], [$formattedTime, $punisher], $this->config['messages']['player_mute']));
        }
    }

    private function unmutePlayer(CommandSender $sender, string $playerName): void {
        if (!$this->data->exists($playerName)) {
            $sender->sendMessage($this->config['messages']['not_muted']);
            if ($sender instanceof Player) {
                $this->playSound($sender, "note.didgeridoo");
            }
            return;
        }

        $this->data->remove($playerName);
        $this->data->save();

        if ($sender instanceof Player) {
            $sender->sendMessage($this->config['messages']['unmute_success']);
            $this->playSound($sender, "random.levelup");
        } else {
            $this->getLogger()->info($this->config['messages']['unmute_success']);
        }
    }

    private function muteAllPlayers(CommandSender $sender): void {
        if ($this->data->get('muteall', false)) {
            $sender->sendMessage($this->config['messages']['already_muted_all']);
            if ($sender instanceof Player) {
                $this->playSound($sender, "note.didgeridoo");
            }
            return;
        }

        $this->data->set('muteall', true);
        $this->data->save();
        $this->getServer()->broadcastMessage($this->config['messages']['broadcast_mute_all']);
        $this->broadcastSound("note.bass");
    }

    private function unmuteAllPlayers(CommandSender $sender): void {
        if (!$this->data->get('muteall', false)) {
            $sender->sendMessage($this->config['messages']['not_muted_all']);
            if ($sender instanceof Player) {
                $this->playSound($sender, "note.didgeridoo");
            }
            return;
        }

        $this->data->set('muteall', false);
        $this->data->save();
        $this->getServer()->broadcastMessage($this->config['messages']['broadcast_unmute_all']);
        $this->broadcastSound("random.orb");
    }

    private function checkMuteStatus(CommandSender $sender, string $playerName): void {
        $player = $this->getServer()->getPlayerExact($playerName);
        if ($player) {
            $playerName = $player->getName();
        }

        $data = $this->data->get($playerName);
        if (!$data) {
            $sender->sendMessage($this->config['messages']['not_muted']);
            if ($sender instanceof Player) {
                $this->playSound($sender, "note.didgeridoo");
            }
            return;
        }

        $remainingTime = $data['time'] - time();
        $formattedTime = $this->timeManager->formatTime($remainingTime);
        $sender->sendMessage(str_replace(['{player}', '{time}', '{punisher}'], [$playerName, $formattedTime, $data['punisher']], $this->config['messages']['mute_check']));

        if ($sender instanceof Player) {
            $this->playSound($sender, "note.bass");
        }
    }

    public function getData(): Config {
        return $this->data;
    }

    public function getPluginConfig(): array {
        return $this->config;
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        $data = $this->data->get($playerName);
        $currentTime = time();

        if ($this->data->get('muteall', false) && !$this->isPlayerOp($player)) {
            $event->cancel();
            if (!isset($this->lastMessageTime[$playerName]) || $currentTime - $this->lastMessageTime[$playerName] >= 1) {
                $player->sendMessage($this->config['messages']['mute_all']);
                $this->playSound($player, "note.didgeridoo");
                $this->lastMessageTime[$playerName] = $currentTime;
            }
            return;
        }

        if ($data && !$this->isPlayerOp($player)) {
            $remainingTime = $data['time'] - $currentTime;
            if ($remainingTime > 0) {
                $event->cancel();
                if (!isset($this->lastMessageTime[$playerName]) || $currentTime - $this->lastMessageTime[$playerName] >= 1) {
                    $player->sendMessage(str_replace('{time}', $this->timeManager->formatTime($remainingTime), $this->config['messages']['player_muted']));
                    $this->playSound($player, "note.didgeridoo");
                    $this->lastMessageTime[$playerName] = $currentTime;
                }
            } else {
                $this->data->remove($playerName);
                $this->data->save();
            }
        }
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        // Removed OP logging
    }

    public function playSound(Player $player, string $soundName): void { // Changed to public
        $pk = new PlaySoundPacket();
        $pk->soundName = $soundName;
        $pk->x = $player->getPosition()->getX();
        $pk->y = $player->getPosition()->getY();
        $pk->z = $player->getPosition()->getZ();
        $pk->volume = 1;
        $pk->pitch = 1;
        $player->getNetworkSession()->sendDataPacket($pk);
    }

    private function broadcastSound(string $soundName): void {
        $pk = new PlaySoundPacket();
        $pk->soundName = $soundName;
        $pk->volume = 1;
        $pk->pitch = 1;
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $pk->x = $player->getPosition()->getX();
            $pk->y = $player->getPosition()->getY();
            $pk->z = $player->getPosition()->getZ();
            $player->getNetworkSession()->sendDataPacket($pk);
        }
    }
}