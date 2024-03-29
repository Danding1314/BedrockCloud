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

    public function onTick(int $tick): void
    {
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
                        if ($packet instanceof DisconnectPacket) {
                            $this->logger->info("§f[§b{$packet->requestId}§f] §cHas disconnected due to §f{$packet->reason}");

                            $key = array_search($packet->requestId, $this->clients);
                            unset($this->clients[$key]);

                        }
                        if ($packet instanceof LoginPacket) {
                            $this->logger->info("Received LoginPacket from {$address->getIp()}:{$address->getPort()}.");
                            //RECEIVED LOGIN PACKET: CHECK IF PASSWORD MATCHES
                            if ($packet->password == $this->password) {
                                $this->clients[$packet->requestid] = $address;
                                $this->acceptConnection();
                                $this->logger->info("{$address->getIp()}:{$address->getPort()} was approved.");
                            } else {
                                $this->disconnect($address, DisconnectPacket::REASON_WRONG_PASSWORD);
                                $this->logger->alert("{$address->getIp()}:{$address->getPort()} was denied, reason password is wrong.");
                            }
                        } else {
                            //FOR OTHER PACKETS, CHECK IF CLIENT IS AUTHENTICATED
                            if (in_array($address, $this->clients)) {
                                if ($packet instanceof DataPacket) {
                                    if ($packet instanceof RequestPacket) {
                                        if ($packet->type == RequestPacket::TYPE_REQUEST) {
                                            if ($packet instanceof StartServerPacket) {
                                                $this->handleStartServerPacket($address, $packet->requestId, $packet->template, $packet->count);
                                            } else if ($packet instanceof StopServerPacket) {
                                                $this->handleStopServerPacket($address, $packet->requestId, $packet->server);
                                            } else if ($packet instanceof StopServerGroupPacket) {
                                                $this->handleStopServerGroupPacket($address, $packet->requestId, $packet->template);
                                            } else if ($packet instanceof MessagePacket) {
                                                $this->handleSendMessage($packet->message);
                                            } else if ($packet instanceof ConsoleTextPacket) {
                                                $this->logger->info("§f[§b{$packet->sender}§f] §f{$packet->message}");
                                            }
                                        }
                                    }
                                } else {
                                    $this->cloud->getServer()->getLogger()->info("Got unknown packet. Ignoring...");
                                }
                            } else {
                                $this->cloud->getServer()->getLogger()->info("Got packet from unauthorized server. Ignoring...");
                            }
                        }
                    }
                }
            }
        }
    }

	/**
	 * Function getSocket
	 * @return UDPServerSocket
	 */
	public function getSocket(){
		return $this->socket;
	}
}