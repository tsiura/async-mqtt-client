<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\PacketStream;

readonly class MQTTFrame
{
    public function __construct(
        public int $control,
        public int $remainingLength = 0,
        public string $content = '',
    ) {
    }

    public function encode(): string
    {
        return $this->toStream()->getContent();
    }

    private function toStream(): PacketStream
    {
        return (new PacketStream())
            ->writeByte($this->control)
            ->writeRemainingLength($this->remainingLength)
            ->writeString($this->content);
    }
}
