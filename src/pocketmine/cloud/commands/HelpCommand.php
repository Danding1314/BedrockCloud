<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\Options;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;

class HelpCommand extends VanillaCommand {
	public $cloud;

	public function __construct(Cloud $cloud, string $name) {
		$this->cloud = $cloud;
		parent::__construct($name, "Stop cloud", "/end");
	}

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        $sender->sendMessage(Options::PREFIX . "§7-=- §bHelp Page §7-=-");

        $descriptions = [];
        $commands = $this->cloud->getServer()->getCommandMap()->getCommands();

        foreach($commands as $name => $command) {
            if (in_array($command->getDescription(), $descriptions)) continue;
            $descriptions[] = $command->getDescription();
            $sender->sendMessage(Options::PREFIX . "§7- §e{$command} §c| §r{$command->getDescription()}");
        }
        $counting = count($commands);
        $result1 = $counting / 2;
        $result = $result1 -1;
        $sender->sendMessage($result . " Commands");
    }
}
