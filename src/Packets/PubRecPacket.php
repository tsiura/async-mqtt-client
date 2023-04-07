<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

class PubRecPacket extends PubAckPacket
{
    public static function getPacketType(): int
    {
        return self::PACKET_PUBREC;
    }
}
