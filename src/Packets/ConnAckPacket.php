<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class ConnAckPacket extends AbstractPacket
{
    public function __construct(
        public readonly int $retCode,
        public readonly bool $sessPresent
    ) {
    }

    public static function getPacketType(): int
    {
        return self::PACKET_CONNACK;
    }

    /**
     * @param MQTTFrame $frame
     * @return PacketInterface
     * @throws MqttPacketException
     */
    public static function fromFrame(MQTTFrame $frame): PacketInterface
    {
        parent::validatePacketType($frame->control);

        $content = new PacketStream($frame->content);

        $sessPresent = $content->readByte() << 7 !== 0;
        $retCode = $content->readByte();

        return new self($retCode, $sessPresent);
    }

    public function toFrame(): MQTTFrame
    {
        $content = new PacketStream();
        $content->writeByte($this->sessPresent ? 1 : 0);
        $content->writeByte($this->retCode);

        return new MQTTFrame(
            self::getPacketType() << 4,
            $content->getLength(),
            $content->getContent()
        );
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);
        return sprintf(
            '[%s] retCode: %d, sessPresent: %d',
            $c->getShortName(),
            $this->retCode,
            $this->sessPresent,
        );
    }
}
