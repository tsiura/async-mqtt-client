<?php

namespace Tests\Unit\Packet;

use Tsiura\MqttClient\Packets\PacketInterface;
use Tsiura\MqttClient\Packets\PublishPacket;
use PHPUnit\Framework\TestCase;

class PublishPacketTest extends TestCase
{
    public function testWithQos0(): void
    {
        $topic = 'test/topic';
        $msg = 'hello world';

        $packet = new PublishPacket($topic, $msg, 0);
        $packet->packetId = 10;

        $frame = $packet->toFrame();

        self::assertEquals(PacketInterface::PACKET_PUBLISH, $frame->control >> 4);
        self::assertEquals(23, strlen($frame->content));
        self::assertEquals(23, $frame->remainingLength);
        self::assertEquals(25, strlen($frame->encode()));
    }

    public function testWithQos1(): void
    {
        $topic = 'test/topic';
        $msg = 'hello world';

        $packet = new PublishPacket($topic, $msg, 2);
        $packet->packetId = 10;

        $frame = $packet->toFrame();

        self::assertEquals(PacketInterface::PACKET_PUBLISH, $frame->control >> 4);
        self::assertEquals(25, strlen($frame->content));
        self::assertEquals(25, $frame->remainingLength);
        self::assertEquals(27, strlen($frame->encode()));
    }

    public function testEncodeDecode()
    {
        $topic = 'test/topic';
        $msg = 'hello world';

        $packet = new PublishPacket($topic, $msg, 1);
        $packet->packetId = 10;

        $content = $packet->toFrame()->content;

        self::assertEquals(
            $content,
            PublishPacket::fromFrame($packet->toFrame())->toFrame()->content
        );
    }
}
