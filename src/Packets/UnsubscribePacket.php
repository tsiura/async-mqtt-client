<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class UnsubscribePacket extends AbstractPacket
{
    private array $topics;

    use PacketIdAwareTrait;

    public function __construct(int $packetId)
    {
        $this->packetId = $packetId;
    }

    public static function getPacketType(): int
    {
        return self::PACKET_UNSUBSCRIBE;
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
            while (($length = $content->readWord())) {
                $packet->addTopic($content->readString($length));
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

        foreach ($this->getTopics() as $topic) {
            $content->writeWord(strlen($topic));
            $content->writeString($topic);
        }

        $ctrl = self::getPacketType() << 4;
        $ctrl |= (1 << 1);

        return new MQTTFrame(
            $ctrl,
            $content->getLength(),
            $content->getContent()
        );
    }

    public function addTopic(string $topic): self
    {
        $this->topics[$topic] = $topic;

        return $this;
    }

    public function getTopics(): array
    {
        return $this->topics;
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);

        return sprintf('[%s]', $c->getShortName());
    }
}
