<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class SubAckPacket extends AbstractPacket
{
    public const FAILURE = 0x80;

    public function __construct(
        public readonly int $packetId,
        public readonly array $retCodes,
    ) {
    }

    public static function getPacketType(): int
    {
        return self::PACKET_SUBACK;
    }

    /**
     * @param MQTTFrame $frame
     * @return static
     * @throws MqttPacketException
     */
    public static function fromFrame(MQTTFrame $frame): static
    {
        parent::validatePacketType($frame->control);

        $content = new PacketStream($frame->content);

        $packetId = $content->readWord();
        $retCodes = [];
        while (null !== ($code = $content->readByte())) {
            $retCodes[] = $code;
        }

        return new self($packetId, $retCodes);
    }

    public function toFrame(): MQTTFrame
    {
        $content = new PacketStream();
        $content->writeWord($this->packetId);

        foreach ($this->retCodes as $code) {
            $content->writeByte($code);
        }

        $ctrl = self::getPacketType() << 4;
        $ctrl |= (1 << 1);

        return new MQTTFrame(
            $ctrl,
            $content->getLength(),
            $content->getContent()
        );
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);

        return sprintf(
            '[%s] packetId: %d, retCodes: %s',
            $c->getShortName(),
            $this->packetId,
            implode(',', $this->retCodes),
        );
    }
}
