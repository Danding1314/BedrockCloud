<?php
namespace pocketmine\cloud\network;
use pocketmine\cloud\Cloud;
use pocketmine\cloud\CloudServer;
use pocketmine\cloud\network\protocol\ConsoleTextPacket;
use pocketmine\cloud\network\protocol\DataPacket;
use pocketmine\cloud\network\protocol\DisconnectPacket;
use pocketmine\cloud\network\protocol\ListServersPacket;
use pocketmine\cloud\network\protocol\LoginPacket;
use pocketmine\cloud\network\protocol\MessagePacket;
use pocketmine\cloud\network\protocol\RequestPacket;
use pocketmine\cloud\network\protocol\StartServerPacket;
use pocketmine\cloud\network\protocol\StopServerGroupPacket;
use pocketmine\cloud\network\protocol\StopServerPacket;
use pocketmine\scheduler\ClosureTask;
use raklib\server\UDPServerSocket;
use raklib\utils\InternetAddress;

class BaseHost extends PacketHandler {
    /** @var InternetAddress[] */
    public $clients = [];

	/**
	 * BaseHost constructor.
	 * @param Cloud $cloud
	 * @param InternetAddress $address
	 * @param string $password
	 */
    public function __construct(Cloud $cloud, InternetAddress $address, string $password) {
        $this->cloud = $cloud;
		$this->logger = $cloud->getServer()->getLogger();
        $this->socket = new UDPServerSocket($address);
        $this->address = $address;
        $this->password = $password;
		$this->clients = [];

        PacketPool::init();

        $cloud->getScheduler()->scheduleRepeatingTask(new ClosureTask(function (int $currentTick): void {$this->onTick($currentTick);}), 1);
    }

    public function onTick(int $tick): void{
		if (!$this->closed) {
			$address = $this->address;
			$len = 1;
			while (!$len === false) {
				$len = $this->socket->readPacket($buffer, $address->ip, $address->port);
				if (!$len === false) {
					$this->logger->warning("Received Packet.");
					$packet = PacketPool::getPacket($buffer);
					if (!is_null($packet)) {
						$packet->decode();
						if ($packet instanceof ConsoleTextPacket) {
							$this->logger->info("§f[§b{$packet->sender}§f] §f{$packet->message}");
						}
						if ($packet instanceof DisconnectPacket) {
							$this->logger->info("§f[§b{$packet->requestId}§f] §cHas disconnected due to §f{$packet->reason}");

							$key = array_search($packet->requestId, $this->clients);
							unset($this->clients[$key]);

						}
						if ($packet instanceof StartServerPacket) {
							$this->handleStartServerPacket($address, $packet->requestId, $packet->template, $packet->count);
						}
						if ($packet instanceof StopServerPacket) {
							$this->handleStopServerPacket($address, $packet->requestId, $packet->server);
						}
						if ($packet instanceof StopServerGroupPacket) {
							$this->handleStopServerGroupPacket($address, $packet->requestId, $packet->template);
						}
                        if ($packet instanceof MessagePacket) {
                            $this->handleSendMessage($packet->message);
                        }
						if ($packet instanceof RequestPacket && $packet->type == RequestPacket::TYPE_REQUEST) {
							if ($packet instanceof LoginPacket) {
								$this->logger->info("Received LoginPacket from {$address->getIp()}:{$address->getPort()}.");
								//RECEIVED LOGIN PACKET: CHECK IF PASSWORD MATCHES
								if ($packet->password == $this->password) {
									$this->clients[$packet->requestid] = $address;
									$this->acceptConnection();
									$this->logger->info("{$address->getIp()}:{$address->getPort()} was approved.");
								} else {
									#$this->disconnect($address, DisconnectPacket::REASON_WRONG_PASSWORD);
									$this->logger->alert("{$address->getIp()}:{$address->getPort()} was denied, reason password is wrong.");
								}
							} else {
								//FOR OTHER PACKETS, CHECK IF CLIENT IS AUTHENTICATED
								/*if (in_array($address, $this->clients)) {
									if($packet instanceof ListServersPacket){
										$this->listServers($address, $packet->requestid, $packet->template);
									}elseif($packet instanceof StartServerPacket){
										$this->listServers($address, $packet->requestid, $packet->count, $packet->template);
									}
								} else {
									$this->cloud->getServer()->getLogger()->info("Got packet from unauthorized server. Ignoring...");
								}*/
							}
						}
					}
				}
			}
		}
	}

    public function getServerIdByAddress(InternetAddress $address): string {
        return array_search($address, $this->clients);
    }

    public function acceptConnection() {
        #$pk = new AcceptConnectionPacket();
        #$this->sendPacket($pk);
    }

    public function listServers(InternetAddress $address, string $id, string $temp = ""){
        $servers = [];
        if($this->cloud->getTemplateByName($temp)){
            $servers = array_merge($servers, $this->cloud->getTemplateByName($temp)->getServers());
        }else{
            $templates = $this->cloud->getTemplates();
            foreach ($templates as $template){
                $servers = array_merge($servers, $template->getServers());
            }
        }
        $s = [];
        foreach ($servers as $server){
            $s[] = [
                "id" => $server->getID(),
                "template" => $server->getTemplate()->getName(),
                "playercount" => $server->getPlayerCount(),
                "maxplayers" => $server->getTemplate()->maxPlayerCount
            ];
        }

        $pk = new ListServersPacket();
        $pk->type = RequestPacket::TYPE_RESPONSE;
        $pk->requestid = $id;
        $pk->servers = $s;
        $pk->template = $temp;
        $this->sendPacket($pk, $address);
    }

    public function startServer(InternetAddress $address, string $id, int $count, string $temp = ""){
        $pk = new StartServerPacket();
        $pk->type = RequestPacket::TYPE_RESPONSE;
        $pk->requestid = $id;
        if($this->cloud->isTemplate($temp)){
            $template = $this->cloud->getTemplateByName($temp);
            for ($i = 0; $i < $count; $i++){
                $server = $template->createNewServer();
                $server->startServer();
            }
            $pk->status = 1;
        }else{
            $pk->status = 0;
        }
        $this->sendPacket($pk, $address);
    }

    public function disconnect(InternetAddress $address, int $reason) {
        if (in_array($address, $this->clients)) {
            $this->clients = array_diff($this->clients, [$address]);
        }

        $pk = new DisconnectPacket();
        $pk->reason = $reason;
        $this->sendPacket($pk, $address);
    }

	/**
	 * Function getSocket
	 * @return UDPServerSocket
	 */
	public function getSocket(){
		return $this->socket;
	}
}