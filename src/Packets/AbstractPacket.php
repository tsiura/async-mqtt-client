<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;

abstract class AbstractPacket implements PacketInterface
{
    /**
     * @param int $ctrl
     * @throws MqttPacketException
     */
    protected static function validatePacketType(int $ctrl)
    {
        if (static::getPacketType() !== $ctrl >> 4) {
            throw new MqttPacketException(
                sprintf('Wrong packet type [%d]', $ctrl >> 4)
            );
        }
    }
}
