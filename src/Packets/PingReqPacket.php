<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use ReflectionClass;

class PingReqPacket extends AbstractPacket
{
    public static function getPacketType(): int
    {
        return self::PACKET_PINGREQ;
    }

    /**
     * @param MQTTFrame $frame
     * @return PacketInterface
     * @throws MqttPacketException
     */
    public static function fromFrame(MQTTFrame $frame): PacketInterface
    {
        parent::validatePacketType($frame->control);

        return new self();
    }

    public function toFrame(): MQTTFrame
    {
        return new MQTTFrame(
            self::getPacketType() << 4
        );
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);

        return sprintf('[%s]', $c->getShortName());
    }
}
