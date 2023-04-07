<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class SubscribePacket extends AbstractPacket
{
    use PacketIdAwareTrait;

    private array $topics = [];

    public function __construct(int $packetId)
    {
        $this->packetId = $packetId;
    }

    public static function getPacketType(): int
    {
        return self::PACKET_SUBSCRIBE;
    }

    /**
     * @param MQTTFrame $frame
     * @return PacketInterface
     * @throws MqttPacketException
     */
    public static function fromFrame(MQTTFrame $frame): PacketInterface
    {
        parent::validatePacketType($frame->control);

        if ($frame->control & (1 << 1) === 0) {
            throw new MqttPacketException('Invalid packet fixed header');
        }

        $content = new PacketStream($frame->content);

        $packetId = $content->readWord();

        $packet = new self($packetId);

        try {
            while (null !== ($length = $content->readWord())) {
                $packet->addTopic($content->readString($length), $content->readByte());
            }
        } catch (\Exception $e) {
            // Out of range exception
        }

        return $packet;
    }

    public function toFrame(): MQTTFrame
    {
        $content = new PacketStream();
        $content->writeWord($this->packetId);

        foreach ($this->topics as $topic => $qos) {
            $content->writeWord(strlen($topic));
            $content->writeString($topic);
            $content->writeByte($qos);
        }

        $ctrl = self::getPacketType() << 4;
        $ctrl |= (1 << 1);

        return new MQTTFrame(
            $ctrl,
            $content->getLength(),
            $content->getContent()
        );
    }

    /**
     * @param string $topic
     * @param int $qos
     * @return $this
     * @throws MqttPacketException
     */
    public function addTopic(string $topic, int $qos = 0): self
    {
        if ($qos < self::QOS_AT_MOST_ONCE || $qos > self::QOS_EXACTLY_ONCE) {
            throw new MqttPacketException(sprintf('Invalid QoS - %d', $qos));
        }

        $this->topics[$topic] = $qos;

        return $this;
    }

    public function getTopics(): array
    {
        return $this->topics;
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);
        $t = [];
        foreach ($this->topics as $topic => $qos) {
            $t[] = sprintf('[%s][%s]', $topic, $qos);
        }

        return sprintf('[%s] packetId: %d, topics: %s', $c->getShortName(), $this->packetId, implode(',', $t));
    }
}
