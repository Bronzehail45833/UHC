<?php

namespace uhc\scenario;

use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\item\Item;
use uhc\Loader;

class CutClean extends Scenario{

	public function __construct(Loader $plugin){
		parent::__construct($plugin, "CutClean");
	}

	public function handleBreak(BlockBreakEvent $ev) : void{
		if($this->isActive()){
			switch($ev->getBlock()->getId()){
				case Block::IRON_ORE:
					$ev->setXpDropAmount(1); //TODO: 70% chance
					$ev->setDrops([Item::get(Item::IRON_INGOT)]);
					break;
				case Block::GOLD_ORE:
					$ev->setXpDropAmount(1);
					$ev->setDrops([Item::get(Item::GOLD_INGOT)]);
					break;
			}
		}
	}
}