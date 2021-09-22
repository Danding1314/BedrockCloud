<?php
/* Copyright (c) 2020 Florian H. All rights reserved. */
namespace pocketmine\cloud\network\protocol;


/**
 * Class Packet
 * @package pocketmine\cloud\network\protocol
 * @author Florian H.
 * @date 01.08.2020 - 21:25
 * @project CloudServer
 */
class Packet extends DataPacket{
	public const TYPE_REQUEST                = 0; //ANTI-CONFUSION: request(to-cloud)
	public const TYPE_RESPONSE               = 1; //ANTI-CONFUSION: answer(from-cloud)

	public const STATUS_SUCCESS              = 0; //ANTI-CONFUSION: success
	public const STATUS_ERROR                = 1; //ANTI-CONFUSION: error

	public const BOOL_TRUE                   = 0; //ANTI-CONFUSION: true
	public const BOOL_FALSE                  = 1; //ANTI-CONFUSION: false

	public const PACKET_LOGIN             	  = 0x0000A;
	public const PACKET_DISCONNECT        	  = 0x0000B;
	public const PACKET_ACCEPT_CONNECTION 	  = 0x0000C;
	public const PACKET_LOG               	  = 0x0000D;
	public const PACKET_START_SERVER      	  = 0x0000E;
	public const PACKET_STOP_SERVER       	  = 0x0000F;
	public const PACKET_STOP_GROUP       	  = 0x000A0;
    public const PACKET_MESSAGE_ALL           = 0x000A1;
    public const PACKET_MESSAGE               = 0x000A2;
}
