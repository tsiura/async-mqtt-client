<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class PublishPacket extends AbstractPacket
{
    use PacketIdAwareTrait;

    public function __construct(
        public readonly string $topic,
        public readonly string $message = '',
        public readonly int $qos = 0,
        public bool $dup = false,
        public readonly bool $retain = false,
    ) {
    }

    public static function getPacketType(): int
    {
        return self::PACKET_PUBLISH;
    }

    /**
     * @param MQTTFrame $frame
     * @return PacketInterface
     * @throws MqttPacketException
     */
    public static function fromFrame(MQTTFrame $frame): PacketInterface
    {
        parent::validatePacketType($frame->control);

        $ctrl = $frame->control;

        $retain = ($ctrl & (1 << 0)) > 0;
        $qos = ($ctrl & (1 << 1)) > 0
            ? self::QOS_AT_LEAST_ONCE
            : (($ctrl & (1 << 2)) > 0 ? self::QOS_EXACTLY_ONCE : self::QOS_AT_MOST_ONCE);
        $dup = ($ctrl & (1 << 3)) > 0;

        $content = new PacketStream($frame->content);

        $topic = $content->readString($content->readWord());

        $packetId = null;
        if ($qos > self::QOS_AT_MOST_ONCE) {
            $packetId = $content->readWord();
        }

        $message = $content->readToEnd();

        $packet = new self($topic, $message, $qos, $dup, $retain);

        if (null !== $packetId) {
            $packet->packetId = $packetId;
        }

        return $packet;
    }

    /**
     * @return MQTTFrame
     * @throws MqttPacketException
     */
    public function toFrame(): MQTTFrame
    {
        if (empty($this->topic)) {
            throw new MqttPacketException('Topic name can not be empty');
        }

        $content = new PacketStream();
        $content->writeWord(strlen($this->topic));
        $content->writeString($this->topic);

        if ($this->qos > self::QOS_AT_MOST_ONCE) {
            if (empty($this->packetId)) {
                throw new MqttPacketException('PacketId required with QoS > 0');
            }
            $content->writeWord($this->packetId);
        }

        $content->writeString($this->message);

        $ctrl = self::getPacketType() << 4;
        $ctrl |= (int)$this->getFixedHeaderReservedBits();

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
            '[%s] packetId: %d, topic: %s, message: %s, qos: %d, dup: %d, retain: %d',
            $c->getShortName(),
            $this->packetId,
            $this->topic,
            $this->message,
            $this->qos,
            $this->dup,
            $this->retain,
        );
    }

    protected function getFixedHeaderReservedBits(): string
    {
        $byte = 0;
        if ($this->retain) {
            $byte |= (1 << 0);
        }
        if ($this->qos > 0) {
            $byte |= ($this->qos << 1);
        }
        if ($this->dup) {
            $byte |= (1 << 3);
        }

        return (string)$byte;
    }
}
