<?php

namespace pocketmine\cloud\commands;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\Options;
use pocketmine\command\CommandSender;
use pocketmine\command\defaults\VanillaCommand;
use pocketmine\utils\UUID;

class StopCommand extends VanillaCommand {
    public $cloud;

    public function __construct(Cloud $cloud, string $name) {
        $this->cloud = $cloud;
        parent::__construct($name, "Stop servers", "/stop <template>|<server>|all");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args) {
        if(isset($args[0])){
            if ($args[0] == "all"){
				$this->cloud->stopAll();
                $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cStopping all servers.");
            }elseif($this->cloud->isTemplate($args[0])){
                $this->cloud->getServer()->getLogger()->info(Options::PREFIX . "§cStopping all servers with template ".$args[0]."!");
                $template = $this->cloud->getTemplateByName($args[0]);
                $template->stopAllServers();
            }elseif ($this->cloud->getServerByID($args[0])){
            	$server = $this->cloud->getServerByID($args[0]);
				$server->stopServer();
            }else{
                $sender->sendMessage($this->getUsage());
            }
        }else{
            $sender->sendMessage($this->getUsage());
        }
    }
}
