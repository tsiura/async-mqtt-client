<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

trait PacketIdAwareTrait
{
    public int $packetId = 0;
}
