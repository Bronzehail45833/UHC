<?php

declare(strict_types=1);

namespace uhc;

use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat as TF;
use uhc\event\UHCStartEvent;
use uhc\utils\Border;
use uhc\utils\GameStatus;
use uhc\utils\RegionUtils;
use function count;
use function floor;
use function gmdate;
use function mt_rand;

class GameHeartbeat extends Task {
	/** @var int */
	private $gameStatus = GameStatus::WAITING;

	/** @var int */
	private $game = 0;
	/** @var int */
	private $countdown = 30;
	/** @var float|int */
	private $grace = 60 * 20;
	/** @var float|int */
	private $pvp = 60 * 30;
	/** @var float|int */
	private $normal = 60 * 60;
	/** @var Border */
	private $border;
	/** @var Loader */
	private $plugin;

	/** @var int */
	private $playerTimer = 1;

	public function __construct(Loader $plugin) {
		$this->plugin = $plugin;
		$this->border = new Border($plugin->getServer()->getDefaultLevel());
	}

	public function getPlugin() : Loader{
		return $this->plugin;
	}

	public function getGameStatus() : int{
		return $this->gameStatus;
	}

	public function setGameStatus(int $gameStatus) : void{
		$this->gameStatus = $gameStatus;
	}

	public function hasStarted() : bool{
		return $this->getGameStatus() >= GameStatus::GRACE;
	}

	public function onRun(int $currentTick) : void{
		$this->handlePlayers();
		switch($this->getGameStatus()){
			case GameStatus::COUNTDOWN:
				$this->handleCountdown();
				break;
			case GameStatus::GRACE:
				$this->handleGrace();
				break;
			case GameStatus::PVP:
				$this->handlePvP();
				break;
			case GameStatus::NORMAL:
				$this->handleNormal();
				break;
		}
		if($this->hasStarted()) $this->game++;
	}

	private function handlePlayers() : void{
		foreach($this->getPlugin()->getServer()->getOnlinePlayers() as $p){
			if($p->isSurvival()){
				$this->getPlugin()->addToGame($p);
			}else{
				$this->getPlugin()->removeFromGame($p);
			}
			$this->handleScoreboard($p);
		}

		foreach($this->getPlugin()->getGamePlayers() as $player){
			$player->setScoreTag(floor($player->getHealth()) . TF::RED . " ❤");
			if(!$this->border->isPlayerInsideOfBorder($player)){
				$this->border->teleportPlayer($player);
				$player->addTitle("§f§k.§r§a§o§lTeleported to border§r§f§k.");
			}
			switch($this->getGameStatus()) {
				case GameStatus::COUNTDOWN:
					$player->setFood($player->getMaxFood());
					$player->setHealth($player->getMaxHealth());
					if($this->countdown === 29){
						$this->randomizeCoordinates($player, 750);
						$player->setWhitelisted(true);
						$player->removeAllEffects();
						$player->getInventory()->clearAll();
						$player->getArmorInventory()->clearAll();
						$player->getCursorInventory()->clearAll();
						$player->setImmobile(true);
					}elseif($this->countdown === 3){
						$player->setImmobile(false);
					}
					break;
				case GameStatus::GRACE:
					if($this->grace === 601){
						$player->setHealth($player->getMaxHealth());
					}
					break;
			}
		}
	}

	private function handleCountdown() : void{
		$server = $this->getPlugin()->getServer();
		switch($this->countdown){
			case 30:
				$server->setConfigBool("white-list", true);
				$server->broadcastTitle("§f§k.§r§o§l§eServer has been " . TF::AQUA . "§ewhitelisted!§r§f§k.");
				$server->broadcastTitle("§f§k.§r§b§o§lBegin in " . TF::AQUA . "§b30 seconds.§r§f§k.");
				break;
			case 29:
				$server->broadcastTitle("§f§k.§r§c§o§lGlobal Mute on.§r§f§k.");
				$this->getPlugin()->setGlobalMute(true);
				break;
			case 10:
				$server->broadcastTitle("§f§k.§r§o§b§lStarting in 10 seconds.§r§f§k.");
				break;
			case 5:
			case 4:
			case 3:
			case 2:
			case 1:
				$server->broadcastTitle("§f§k.§r§o§l§bStarting in " . TF::AQUA . "$this->countdown second(s).§r§f§k.");
				break;
			case 0:
				foreach($this->getPlugin()->getServer()->getDefaultLevel()->getEntities() as $entity){
					if(!$entity instanceof Player){
						$entity->flagForDespawn();
					}
				}
				$ev = new UHCStartEvent($this->getPlugin()->getGamePlayers());
				$ev->call();
				$server->broadcastTitle(TF::RED . TF::BOLD . "§f§k.§r§a§o§lGO!§r§f§k.");
				$this->setGameStatus(GameStatus::GRACE);
				$this->countdown = 30;
				break;
		}
		$this->countdown--;
	}

	private function handleGrace() : void{
		$this->grace--;
		$server = $this->getPlugin()->getServer();
		switch($this->grace){
			case 1190:
				$server->broadcastTitle("§f§k.§r§c§o§lGlobal Mute off.§r§f§k.");
				$this->getPlugin()->setGlobalMute(false);
				$server->broadcastTitle("§f§k.§r§o§l§bFinal heal in " . TF::AQUA . "10 minutes.§r§f§k.");
				break;
			case 601:
				$server->broadcastTitle("§f§k.§r§o§l§bFinal heal has been given.§r§f§k.");
				break;
			case 600:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§bPvP in 10 mins.§r§f§k.");
				break;
			case 300:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§bPvP in 5 mins.§r§f§k.");
				break;
			case 60:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§bPvP in 1 min.§r§f§k.");
				break;
			case 30:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§bPvP in 30 sec.§r§f§k.");
				break;
			case 10:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§bPvP in 10 sec.§r§f§k.");
				break;
			case 5:
			case 4:
			case 3:
			case 2:
			case 1:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§c $this->grace §r§f§k.");
				break;
			case 0:
				$server->broadcastTitle(TF::RED . "§f§k.§r§o§l§cPvP on!§r§f§k.");
				$this->setGameStatus(GameStatus::PVP);
				$this->grace = 60 * 20;
				break;
		}
	}

	private function handlePvP() : void{
		$this->pvp--;
		$server = $this->getPlugin()->getServer();
		switch($this->pvp){
			case 900:
				$server->broadcastTitle("§f§k.§rThe border will shrink to " . TF::AQUA . "750" . TF::WHITE . " in " . TF::AQUA . "5 minutes.§r§f§k.");
				break;
			case 600:
				$this->border->setSize(750);
				$server->broadcastTitle("§f§k.§rThe border has shrunk to " . TF::AQUA . $this->border->getSize() . ".\nShrinking to " . TF::AQUA . "500" . TF::WHITE . " in " . TF::AQUA . "5 minutes.§r§f§k.");
				break;
			case 300:
				$this->border->setSize(500);
				$server->broadcastTitle("§f§k.§rThe border has shrunk to " . TF::AQUA . $this->border->getSize() . ".\nShrinking to " . TF::AQUA . "250" . TF::WHITE . " in " . TF::AQUA . "5 minutes.§r§f§k.");
				break;
			case 0:
				$this->border->setSize(250);
				$server->broadcastTitle("§f§k.§rThe border has shrunk to " . TF::AQUA . $this->border->getSize() . ".\nShrinking to " . TF::AQUA . "100" . TF::WHITE . " in " . TF::AQUA . "5 minutes.§r§f§k.");
				$this->setGameStatus(GameStatus::NORMAL);
				$this->pvp = 60 * 30;
				break;
		}
	}

	public function handleNormal() : void{
		$this->normal--;
		$server = $this->getPlugin()->getServer();
		switch($this->normal){
			case 3300:
				$this->border->setSize(100);
				$this->border->build();
				$server->broadcastTitle("§f§k.§rThe border has shrunk to " . TF::AQUA . $this->border->getSize() . ".\nShrinking to " . TF::AQUA . "25" . TF::WHITE . " in " . TF::AQUA . "5 minutes.");
				break;
			case 3000:
				$this->border->setSize(25);
				$this->border->build();
				$server->broadcastTitle("§f§k.§rThe border has shrunk to " . TF::AQUA . $this->border->getSize() . ".\nShrinking to " . TF::AQUA . "10" . TF::WHITE . " in " . TF::AQUA . "5 minutes.");
				break;
			case 2700:
				$this->border->setSize(10);
				$this->border->build();
				$server->broadcastTitle("§f§k.§rThe border has shrunk to " . TF::AQUA . $this->border->getSize() . ".");
				break;
		}
	}

	private function handleScoreboard(Player $p) : void{
		$session = $this->getPlugin()->getSession($p);
		if($session instanceof PlayerSession) {
			if(!$session->getScoreboard()->exists()) {
				$session->getScoreboard()->send("§7[§5§o§lAzure §bUHC§r§7]");
			}
			if($this->hasStarted()){
				$session->getScoreboard()->setLineArray([
					1 => "§7--------------------- ",
					2 => " §bGame Time: §f" . gmdate("H:i:s", $this->game),
					3 => " §bRemaining: §f" . count($this->getPlugin()->getGamePlayers()),
					4 => " §bEliminations: §f" . $this->getPlugin()->getEliminations($p),
					5 => " §bBorder: §f" . $this->border->getSize(),
					6 => " §bCenter: §f(" . $p->getLevel()->getSafeSpawn()->getFloorX() . ", " . $p->getLevel()->getSafeSpawn()->getFloorZ() . ")",
					7 => "§7--------------------- "
				]);
			}else{
				$session->getScoreboard()->setLineArray([
					1 => "§7--------------------- ",
					2 => " §bPlayers: §f" . count($this->getPlugin()->getGamePlayers()),
					3 => $this->getGameStatus() === GameStatus::WAITING ? "§b Waiting for players..." : "§b Starting in:§f $this->countdown",
					4 => "§7--------------------- "
				]);
			}
		}
	}

	private function randomizeCoordinates(Player $p, int $range) : void{
		$this->getPlugin()->getScheduler()->scheduleDelayedTask(new ClosureTask(function(int $currentTick) use ($p, $range) : void{
			$ss = $p->getLevel()->getSafeSpawn();
			$x = mt_rand($ss->getFloorX() - $range, $ss->getFloorX() + $range);
			$z = mt_rand($ss->getFloorZ() - $range, $ss->getFloorZ() + $range);

			RegionUtils::onChunkGenerated($p->getLevel(), $x >> 4, $z >> 4, function() use ($p, $x, $z){
				$p->teleport(new Vector3($x, $p->getLevel()->getHighestBlockAt($x, $z) + 1, $z));
			});

			$this->playerTimer += 5;
		}), $this->playerTimer);
	}
}
