<?php

namespace pocketmine\cloud\network;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\CloudServer;
use pocketmine\cloud\network\protocol\AcceptConnectionPacket;
use pocketmine\cloud\network\protocol\DataPacket;
use pocketmine\cloud\network\protocol\DisconnectPacket;
use pocketmine\cloud\network\protocol\ListServersPacket;
use pocketmine\cloud\network\protocol\RequestPacket;
use pocketmine\cloud\network\protocol\SendMessagePacket;
use pocketmine\cloud\network\protocol\StartServerPacket;
use pocketmine\cloud\network\protocol\StopServerPacket;
use pocketmine\cloud\Options;
use raklib\server\UDPServerSocket;
use raklib\utils\InternetAddress;

class PacketHandler{

    /** @var Cloud */
    protected $cloud = null;
    /** @var \AttachableThreadedLogger */
    protected $logger = null;
    /** @var UDPServerSocket */
    protected $socket = null;
    /** @var string */
    protected $password = null;
    /** @var InternetAddress */
    protected $address = null;
    /** @var bool */
    protected $closed = false;
    /** @var InternetAddress[] */
    protected $clients = [];
    /** @var int[] */
    public $ports = [];
    /** @var resource */
    private static $serverSocket = null;

    /**
     * Function getCloud
     * @return Cloud
     */
    public function getCloud(): Cloud{
        return $this->cloud;
    }

    /**
     * Function getLogger
     * @return \AttachableThreadedLogger
     */
    public function getLogger(): \AttachableThreadedLogger{
        return $this->cloud->getServer()->getLogger();
    }

    /**
     * Function sendPacket
     * @param DataPacket $packet
     * @return bool
     */
    public function sendPacket(DataPacket $packet): bool{

        $server = $this->cloud->getServerByID($packet->requestId);

        if ($server === null){
            return false;
        }

        if (!is_null($server->getPort())) {
            $port = $server->getPort();

            if (!$this->closed) {
                if ($packet instanceof RequestPacket) {
                    $packet->type = RequestPacket::TYPE_RESPONSE;
                }
                $packet->encode();
                $this->socket->writePacket($packet->getBuffer(), "127.0.0.1", $port + 1);
            }
        }
        return false;
    }

    /**
     * Function sendPacketToAll
     * @param DataPacket $packet
     * @return bool
     */
    public function sendPacketToAll(DataPacket $packet): bool{
        if (!$this->closed) {
            if ($packet instanceof RequestPacket) {
                $packet->type = RequestPacket::TYPE_RESPONSE;
                if (empty($packet->requestId)) {
                    $packet->requestId = Options::ID;
                }
            }
            $packet->encode();

            if ($this->cloud->getTemplates() !== null) {
                foreach ($this->cloud->getTemplates() as $template) {
                    foreach ($template->getServers() as $server) {
                        if (!is_null($server->getPort())) {
                            $this->socket->writePacket($packet->getBuffer(), "127.0.0.1", $server->getPort() + 1);
                        }
                    }
                }
            }
        }
        return false;
    }

    /**
     * Function handleSendMessage
     * @return void
     */
    public function handleSendMessage(string $message): void{
        $packet = new SendMessagePacket();
        $packet->requestId = Options::ID;
        $packet->type = RequestPacket::TYPE_RESPONSE;
        $packet->message = $message;
        $this->sendPacketToAll($packet);
    }

    /**
     * Function handleStartServerPacket
     * @param string $requestId
     * @param string $group
     * @param int $count
     * @param array $callbackData
     * @return void
     */
    public function handleStartServerPacket(InternetAddress $address, string $requestId, string $group = "", int $count = 1): void{
        $packet = new StartServerPacket();
        $packet->type = RequestPacket::TYPE_RESPONSE;
        $packet->requestId = $requestId;

        if ($this->cloud->isTemplate($group)) {
            $template = $this->cloud->getTemplateByName($group);
            for ($i = 0; $i < $count; $i++) {
                $server = $template->createNewServer();
                if (!is_null($server)) {
                    $server->startServer();
                } else {
                    $packet->status = RequestPacket::STATUS_ERROR;
                }
            }
        } else {
            $this->logger->warning("§cThis is not a Cloud-Group!");
        }
        $this->sendPacket($packet, $address);
    }

    /**
     * Function handleStartServerPacket
     * @param string $requestId
     * @param string $group
     * @param int $count
     * @param array $callbackData
     * @return void
     */
    public function handleStopServerGroupPacket(InternetAddress $address, string $requestId, string $group = ""): void{
        $packet = new StartServerPacket();
        $packet->type = RequestPacket::TYPE_RESPONSE;
        $packet->requestId = $requestId;

        if ($this->cloud->isTemplate($group)) {
            $template = $this->cloud->getTemplateByName($group);
            $template->stopAllServers();
            foreach ($template->getServers() as $server){
                $template->unregisterServer($server);
                $server->deleteServer();
            }
        } else {
            $this->logger->warning("§cThis is not a Cloud-Group!");
        }
        $this->sendPacket($packet, $address);
    }

    /**
     * Function handleStopServerPacket
     * @param InternetAddress $address
     * @param string $requestId
     * @param string $server
     * @return void
     */
    public function handleStopServerPacket(InternetAddress $address, string $requestId, string $server = ""): void{
        $packet = new StopServerPacket();
        $packet->type = RequestPacket::TYPE_RESPONSE;
        $packet->requestId = $requestId;

        if ($server instanceof CloudServer){
            $server->stopServer();
        }

        $this->sendPacket($packet, $address);
    }

    public function getServerIdByAddress(InternetAddress $address): string {
        return array_search($address, $this->clients);
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

    public function acceptConnection() {
        $pk = new AcceptConnectionPacket();
        $this->sendPacket($pk);
    }

}