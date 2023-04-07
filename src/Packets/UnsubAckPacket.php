<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use ReflectionClass;

class UnsubAckPacket extends PubAckPacket
{
    use PacketIdAwareTrait;

    public static function getPacketType(): int
    {
        return self::PACKET_UNSUBACK;
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);

        return sprintf('[%s]', $c->getShortName());
    }
}
