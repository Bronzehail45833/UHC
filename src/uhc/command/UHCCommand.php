<?php

namespace uhc\command;

use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use uhc\form\SimpleForm;
use uhc\Loader;
use uhc\UHCTimer;

class UHCCommand extends PluginCommand{
	/** @var Loader */
	private $plugin;

	public function __construct(Loader $plugin){
		parent::__construct("uhc", $plugin);
		$this->plugin = $plugin;
		$this->setPermission("uhc.command.uhc");
		$this->setUsage("/uhc");
	}


	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender) || !$sender instanceof Player){
			return true;
		}

		$form = new SimpleForm("UHC");
		$form->addButton("Start UHC", function(Player $player, $data){
			if($data === 0){
				if(UHCTimer::$gameStatus >= UHCTimer::STATUS_COUNTDOWN){
					$player->sendMessage(TextFormat::RED . "UHC already started!");
				}
				UHCTimer::$gameStatus = UHCTimer::STATUS_COUNTDOWN;
			}
		});

		$form->addButton("Teleport All", function(Player $player, $data){
			if($data === 1){
				foreach($this->plugin->getServer()->getOnlinePlayers() as $p){
					$p->teleport($player->getPosition());
				}
			}
		});

		$form->addButton("GlobalMute", function(Player $player, $data){
			if($data === 2){
				if(!$this->plugin->isGlobalMuteEnabled()){
					$this->plugin->setGlobalMute(true);
					$this->plugin->getServer()->broadcastMessage(TextFormat::GREEN . "Chat has been disabled by an admin!");
				}else{
					$this->plugin->setGlobalMute(false);
					$this->plugin->getServer()->broadcastMessage(TextFormat::GREEN . "Chat has been enabled by an admin!");
				}
			}
		});

		$sender->sendForm($form);

		return true;
	}
}
