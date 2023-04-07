<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class PubAckPacket extends AbstractPacket
{
    use PacketIdAwareTrait;

    public function __construct(int $packetId)
    {
        $this->packetId = $packetId;
    }

    public static function getPacketType(): int
    {
        return self::PACKET_PUBACK;
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
        $packetId = $content->readWord();

        return new self($packetId);
    }

    public function toFrame(): MQTTFrame
    {
        $content = new PacketStream();
        $content->writeWord($this->packetId);

        return new MQTTFrame(
            self::getPacketType() << 4,
            $content->getLength(),
            $content->getContent()
        );
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);

        return sprintf('[%s] packetId: %d', $c->getShortName(), $this->packetId);
    }
}
