<?php

declare(strict_types=1);

namespace Tsiura\MqttClient;

use Tsiura\MqttClient\Packets\PacketInterface;

class ConnectionOptions
{
    public function __construct(
        public string $uri = '',
        public string $username = '',
        public string $password = '',
        public string $clientId = '',
        public bool $cleanSession = true,
        public string $willTopic = '',
        public string $willMessage = '',
        public int $willQos = PacketInterface::QOS_AT_MOST_ONCE,
        public bool $willRetain = false,
        public int $keepAlive = 0,
    ) {
    }
}
