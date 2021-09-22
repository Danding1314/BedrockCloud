<?php

namespace pocketmine\cloud\network\protocol;

class CallbackPacket extends RequestPacket{
	/** @var int */
	public $status = -1;
	/** @var string */
	public $message = "No error message.";
	/** @var array */
	public $callbackData = [];
    /** @var string */
    public $requestId; //serverId


	/**
	 * Function encodePayload
	 * @return void
	 */
	protected function encodePayload(){
		parent::encodePayload();
		$this->putInt($this->status);
		$this->putString($this->message);
		$this->putString(json_encode($this->callbackData));
        $this->putString($this->requestId);
	}

	/**
	 * Function decodePayload
	 * @return void
	 */
	protected function decodePayload(){
		parent::decodePayload();
		$this->status = $this->getInt();
		$this->message = $this->getString();
		$this->callbackData = json_decode($this->getString(), true);
        $this->requestId = $this->getString();
	}
}
