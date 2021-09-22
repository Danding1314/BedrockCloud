<?php

namespace pocketmine\cloud\network;

use pocketmine\cloud\Cloud;
use pocketmine\cloud\network\protocol\DataPacket;
use pocketmine\cloud\network\protocol\RequestPacket;
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

}