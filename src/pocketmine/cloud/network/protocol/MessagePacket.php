<?php

namespace pocketmine\cloud\network\protocol;

class MessagePacket extends RequestPacket{
    public const NETWORK_ID = self::PACKET_MESSAGE;

    /** @var string */
    public $sender;
    /** @var string */
    public $message;


    /**
     * Function decodePayload
     * @return void
     */
    protected function encodePayload(): void{
        parent::encodePayload();
        $this->putString($this->sender);
        $this->putString($this->message);
    }

    /**
     * Function encodePayload
     * @return void
     */
    protected function decodePayload(): void{
        parent::decodePayload();
        $this->sender = $this->getString();
        $this->message = $this->getString();
    }
}
