<?php

namespace pocketmine\cloud\network\protocol;

class SendMessagePacket extends RequestPacket{
    public const NETWORK_ID = self::PACKET_MESSAGE_ALL;

    /** @var string */
    public $message;


    /**
     * Function decodePayload
     * @return void
     */
    protected function encodePayload(): void{
        parent::encodePayload();
        $this->putString($this->message);
    }

    /**
     * Function encodePayload
     * @return void
     */
    protected function decodePayload(): void{
        parent::decodePayload();
        $this->message = $this->getString();
    }
}