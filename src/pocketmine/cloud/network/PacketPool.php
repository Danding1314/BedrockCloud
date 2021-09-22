<?php
declare(strict_types=1);
namespace pocketmine\cloud\network;

use pocketmine\cloud\network\protocol\AcceptConnectionPacket;
use pocketmine\cloud\network\protocol\ConsoleTextPacket;
use pocketmine\cloud\network\protocol\LoginPacket;
use pocketmine\cloud\network\protocol\DataPacket;
use pocketmine\cloud\network\protocol\DisconnectPacket;
use pocketmine\cloud\network\protocol\MessagePacket;
use pocketmine\cloud\network\protocol\SendMessagePacket;
use pocketmine\cloud\network\protocol\StartServerPacket;
use pocketmine\cloud\network\protocol\StopServerGroupPacket;
use pocketmine\cloud\network\protocol\StopServerPacket;
use pocketmine\utils\Binary;

class PacketPool{
    /** @var \SplFixedArray<DataPacket> */
    protected static $pool = null;

    public static function init() {
        static::$pool = new \SplFixedArray(256);

        static::registerPacket(new LoginPacket());
        static::registerPacket(new AcceptConnectionPacket());
        static::registerPacket(new ConsoleTextPacket());
        static::registerPacket(new DisconnectPacket());
		static::registerPacket(new StartServerPacket());
		static::registerPacket(new StopServerGroupPacket());
		static::registerPacket(new StopServerPacket());
        static::registerPacket(new SendMessagePacket());
        static::registerPacket(new MessagePacket());
    }

    public static function registerPacket(DataPacket $packet) {
        static::$pool[$packet->pid()] = clone $packet;
    }

    public static function getPacketById(int $pid): ?DataPacket {
        return isset(static::$pool[$pid]) ? clone static::$pool[$pid] : null;
    }

    public static function getPacket(string $buffer): ?DataPacket {
        $offset = 0;
        $pk = static::getPacketById(Binary::readUnsignedVarInt($buffer, $offset) & DataPacket::PID_MASK);
        if (!is_null($pk)) {
            $pk->setBuffer($buffer, $offset);
            return $pk;
        }
        return null;
    }
}
