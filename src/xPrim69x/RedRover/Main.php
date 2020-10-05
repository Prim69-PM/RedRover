<?php

namespace xPrim69x\RedRover;

use pocketmine\plugin\PluginBase;
use pocketmine\{entity\Effect,
	entity\EffectInstance,
	event\entity\EntityDamageByEntityEvent,
	event\player\PlayerDeathEvent,
	event\player\PlayerQuitEvent,
	item\enchantment\Enchantment,
	item\enchantment\EnchantmentInstance,
	item\Item,
	level\Position,
	Player,
	scheduler\ClosureTask,
	Server};
use pocketmine\command\{CommandSender, Command};
use pocketmine\event\Listener;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\Config;

class Main extends PluginBase implements Listener{

	private $participants = [];
	private $fighting = [];
	private $redRover = false;
	private $started = false;
	private $rounda = false;
	private $round = 0;

	CONST noEvent = "There is currently no RedRover event going on!";
	CONST usage = "Usage: /rr <create:start:round:join:leave:spectate:end:list>";
	CONST prefix = "§8[§4RedRover§8] ";

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->saveResource("config.yml");
		$config = new Config($this->getDataFolder()."config.yml", Config::YAML);
	}

	//I swear im not this bad of a dev I made this in the middle of the night in a rush :)

	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool{
		if(!isset($args[0])){
			$sender->sendMessage(TF::GREEN . self::usage);
			return true;
		}
		switch(strtolower($args[0])){
			case "create":
				if(!$sender->hasPermission("redrover.create")){
					$sender->sendMessage(TF::DARK_RED . "You do not have permission to use this command!");
					return true;
				}
				if($this->redRover) {
					$sender->sendMessage(TF::RED . "There is already a RedRover event going on!");
					return true;
				}
				$this->redRover = true;
				$sender->sendMessage(TF::GREEN . "You have created a RedRover event!");
				$this->getServer()->broadcastMessage(TF::GREEN . $sender->getName() . " has started a RedRover event!");
				$this->getServer()->broadcastTitle(TF::DARK_RED . "RedRover!", TF::YELLOW . "Do /rr join to join!");
				break;
			case "start":
				if(!$sender->hasPermission("redrover.create")){
					$sender->sendMessage(TF::DARK_RED . "You do not have permission to use this command!");
					return true;
				}
				if($this->started){
					$sender->sendMessage(TF::RED . "The RedRover event has already been started!");
					return true;
				}
				if(!$this->redRover){
					$sender->sendMessage(TF::RED . self::noEvent);
					return true;
				}
				if(count($this->participants) <= 1){
					$sender->sendMessage(TF::RED . "There are not enough participants to start the event!");
					return true;
				}
				$this->started = true;
				$sender->sendMessage(TF::GREEN . "The RedRover event has been started!");
				foreach($this->participants as $participant){
					$world = $this->getConfig()->get("world");
					if(!$this->getServer()->isLevelLoaded($world)){
						$this->getServer()->loadLevel($world);
					}
					$player = $this->getServer()->getPlayer($participant);
					$player->teleport($this->getServer()->getLevelByName($world)->getSafeSpawn());
				}
				break;
			case "round":
				if(!$sender->hasPermission("redrover.create")){
					$sender->sendMessage(TF::DARK_RED . "You do not have permission to use this command!");
					return true;
				}
				if(count($this->participants) > 1){
					list($red, $blue) = array_chunk($this->participants, ceil(count($this->participants) / 2));
				} else {
					$this->endRover();
					return true;
				}
				if($this->rounda){
					$sender->sendMessage(TF::RED . "There is already a round going on!");
					return true;
				}
				$this->rounda = true;
				$player1 = $this->getServer()->getPlayer($red[array_rand($red)]);
				$player2 = $this->getServer()->getPlayer($blue[array_rand($blue)]);
				$this->round++;
				$p1 = $player1->getName(); $p2 = $player2->getName();
				$this->fighting[] = $p1;
				$this->fighting[] = $p2;
				$player1->setImmobile(true); $player2->setImmobile(true);
				$rn = $this->round;
				$world = $this->getConfig()->get("world");
				$worldd = $this->getServer()->getLevelByName($world);
				$this->getServer()->broadcastMessage(self::prefix . TF::YELLOW . "$p1" . TF::GREEN . " is fighting " . TF::YELLOW . "$p2!" . TF::DARK_GREEN . TF::BOLD . " Round $rn");
				$player1->teleport($worldd->getSafeSpawn());
				$player2->teleport($worldd->getSafeSpawn());

				$pos = $world = $this->getConfig()->get("pos");
				$pos2 = $world = $this->getConfig()->get("pos2");

				$player1->teleport(new Position($pos[0], $pos[1],$pos[2],$worldd));
				$player2->teleport(new Position($pos2[0],$pos2[1],$pos2[2],$worldd));
					$player1->sendMessage(TF::GREEN . "The match will begin in 10 seconds, your opponent is " . TF::YELLOW . $p2 . TF::GREEN . " Good luck!");
					$player2->sendMessage(TF::GREEN . "The match will begin in 10 seconds, your opponent is " . TF::YELLOW . $p1 . TF::GREEN . " Good luck!");
					$this->getScheduler()->scheduleDelayedTask(new ClosureTask(
						function (int $currentTick) use ($player1, $player2): void {
							if(!in_array($player1->getName(),$this->fighting)){
								$this->roundOver($player1, $player2);
								$player2->setImmobile(false);
								$this->removeFighting($player2->getName());
								return;
							}
							if(!in_array($player2->getName(), $this->fighting)) {
								$this->roundOver($player2, $player1);
								$player1->setImmobile(false);
								$this->removeFighting($player1->getName());
								return;
							}
							foreach([$player1, $player2] as $players) {
								$players->setImmobile(false);
								$players->sendTitle("", TF::GREEN . "Fight!");
								$this->Kit($players);
							}
						}
					), 200);
				break;
			case "join":
				if(!$this->redRover) {
					$sender->sendMessage(TF::RED . self::noEvent);
					return true;
				}
				if($this->started){
					$sender->sendMessage(TF::RED . "The RedRover event has already started!");
					return true;
				}
				if(!in_array($sender->getName(), $this->participants)){
					$this->participants[] = $sender->getName();
					$sender->sendMessage(TF::GREEN . "You have joined the RedRover event!");
				} else {
					$sender->sendMessage(TF::RED . "You are already in the RedRover event!");
				}
				break;
			case "leave":
				if(!$this->redRover){
					$sender->sendMessage(TF::RED . self::noEvent);
					return true;
				}
				if(in_array($sender->getName(), $this->participants)){
					if(!$this->started){
						$this->removePlayer($sender->getName());
						$sender->sendMessage(TF::RED . "You have left the RedRover event!");
					} else {
						$sender->sendMessage(TF::RED . "The event has already started!");
					}
				} else {
					$sender->sendMessage(TF::RED . "You are not in the RedRover event!");
				}
				break;
			case "spectate":
				if(!$this->redRover) {
					$sender->sendMessage(TF::RED . self::noEvent);
					return true;
				}
				if(!$this->started){
					$sender->sendMessage(TF::RED . "The RedRover event has not been started!");
					return true;
				}
				$world = $this->getConfig()->get("world");
				if($sender->getLevel()->getName() === $world){
					$sender->sendMessage(TF::RED . "You are already in the RedRover world!");
					return true;
				}
				$sender->teleport($this->getServer()->getLevelByName($world)->getSafeSpawn());
				break;
			case "end":
				if(!$sender->hasPermission("redrover.create")){
					$sender->sendMessage(TF::DARK_RED . "You do not have permission to use this command!");
					return true;
				}
				if(!$this->redRover){
					$sender->sendMessage(TF::RED . self::noEvent);
					return true;
				}
				$this->endRover();
				$sender->sendMessage(TF::RED . "You have ended the RedRover event!");
				break;
			case "list":
				if(!$this->redRover){
					$sender->sendMessage(TF::RED . self::noEvent);
					return true;
				}
				if(count($this->participants) == 0){
					$sender->sendMessage(TF::GREEN . "There are currently no participants!");
					return true;
				}
				$pp = rtrim(implode(", ", $this->participants), ",");
				$sender->sendMessage(TF::DARK_GREEN . "Participants: " . TF::GREEN . "$pp");
				break;
			default:
				$sender->sendMessage(TF::GREEN . self::usage);
		}
		return true;
	}

	public function removePlayer(string $string){
		if(($key = array_search($string, $this->participants)) !== false) {
			unset($this->participants[$key]);
		}
	}

	public function removeFighting(string $string){
		if(($key = array_search($string, $this->fighting)) !== false) {
			unset($this->fighting[$key]);
		}
	}

	public function onQuit(PlayerQuitEvent $event){
		if(!$this->redRover) return;
		$player = $event->getPlayer();
		$name = $player->getName();
		if(in_array($name,$this->participants)){
			$this->removePlayer($name);
		}
		if(in_array($name,$this->fighting)){
			$this->removeFighting($name);
		}
	}

	public function onDeath(PlayerDeathEvent $event){
		if(!$this->started) return;
		$player = $event->getPlayer();
		$cause = $player->getLastDamageCause();
		$name = $player->getName();
		$world = $this->getConfig()->get("world");
		if(in_array($name,$this->participants)){
			$this->removePlayer($name);
		}
		if(in_array($name,$this->fighting)){
			$this->removeFighting($name);
		}
		if($cause instanceof EntityDamageByEntityEvent){
			$damager = $cause->getDamager();
			if($damager instanceof Player){
				if($damager->getLevel()->getName() === $world){
					$this->roundOver($player, $damager);
				}
				if(in_array($damager->getName(),$this->fighting)){
					$this->removeFighting($damager->getName());
				}
			}
		}
	}

	public function endRover(){
		if($this->started){
			if(count($this->participants) <= 1){
				$winner = $this->participants[array_key_first($this->participants)];
				$this->getServer()->broadcastMessage(self::prefix . TF::YELLOW . "The RedRover event is now over. The winner is " . TF::GREEN . "$winner!");
			} else {
				$this->getServer()->broadcastMessage(self::prefix . TF::YELLOW . "The RedRover event is over. A winner could not be determined");
			}
			$world = $this->getConfig()->get("world");
			foreach($this->getServer()->getLevelByName($world)->getEntities() as $players){
				if($players instanceof Player){
					$players->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
				}
			}
		}
		$this->redRover = false;
		$this->started = false;
		$this->participants = [];
		$this->round = 0;
	}

	public function roundOver(Player $player, Player $player2){
		if(!$this->rounda) return;
		$world = $this->getConfig()->get("world");
		if(in_array($player->getName(), $this->participants)){
			$winner = $player;
			$loser = $player2;
		} elseif(in_array($player2->getName(), $this->participants)){
			$winner = $player2;
			$loser = $player;
		}
		if($winner->getLevel()->getName() === $world){
			$winner->teleport($this->getServer()->getLevelByName($world)->getSafeSpawn());
			$winner->getInventory()->clearAll();
			$winner->getArmorInventory()->clearAll();
			$this->getServer()->broadcastMessage(self::prefix . TF::YELLOW . $winner->getName() . TF::GREEN . " won the match against " . TF::YELLOW . $loser->getName() . TF::GREEN . "!");
			$this->rounda = false;
		}
	}

	public function Kit(Player $player){
		$inv = $player->getInventory();
		$arm = $player->getArmorInventory();
		$unbreaking = Enchantment::getEnchantment(17);
		$protection = Enchantment::getEnchantment(0);

		$player->addEffect(new EffectInstance(Effect::getEffect(Effect::SPEED), 999999, 0));
		$slot = [Item::get(276), Item::get(368, 0, 16), Item::get(310), Item::get(311), Item::get(312), Item::get(313), Item::get(438, 22, 34)];

		$enchants = [9 => 1, 17 => 10];
		foreach($enchants as $id => $level){
			$slot[0]->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment($id), $level));
		}

		$slot[0]->setCustomName("§l§4RedRover");
		foreach([$slot[2], $slot[3], $slot[4], $slot[5]] as $armorslots){
			$armorslots->addEnchantment(new EnchantmentInstance($protection, 1));
			$armorslots->addEnchantment(new EnchantmentInstance($unbreaking, 10));
			$armorslots->setCustomName("§l§4RedRover");

			$arm->setContents([$slot[2], $slot[3], $slot[4], $slot[5]]);
			$inv->setItem(0, $slot[0]);
			$inv->setItem(1, $slot[1]);
			$inv->addItem($slot[6]);
		}
	}

}
