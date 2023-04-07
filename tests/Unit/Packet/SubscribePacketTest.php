<?php

namespace Tests\Unit\Packet;

use Tsiura\MqttClient\Packets\PacketInterface;
use Tsiura\MqttClient\Packets\SubscribePacket;
use PHPUnit\Framework\TestCase;

class SubscribePacketTest extends TestCase
{
    public function testEncodeDecodeQos0()
    {
        $topic = 'test/topic';
        $packet = new SubscribePacket(99);
        $packet->addTopic($topic, 0);

        $content = $packet->toFrame()->content;

        $this->assertEquals(PacketInterface::PACKET_SUBSCRIBE << 4 | (1 << 1), $packet->toFrame()->control);

        $this->assertEquals(strlen($topic) + 5, $packet->toFrame()->remainingLength);
        $this->assertEquals(99, unpack('n', substr($content, 0, 2))[1]);
        $this->assertEquals(strlen($topic), unpack('n', substr($content, 2, 2))[1]);
        $this->assertEquals($topic, substr($content, 4, strlen($topic)));
        $this->assertEquals(0, ord($content[4 + strlen($topic)]));

        $this->assertEquals($content, SubscribePacket::fromFrame($packet->toFrame())->toFrame()->content);
    }

    public function testSuccessQos1()
    {
        $topic = 'test/topic/1';
        $packet = new SubscribePacket(12);
        $packet->addTopic($topic, 1);

        $content = $packet->toFrame()->content;

        $this->assertEquals(PacketInterface::PACKET_SUBSCRIBE << 4 | (1 << 1), $packet->toFrame()->control);

        $this->assertEquals(strlen($topic) + 5, $packet->toFrame()->remainingLength);
        $this->assertEquals(12, unpack('n', substr($content, 0, 2))[1]);
        $this->assertEquals(strlen($topic), unpack('n', substr($content, 2, 2))[1]);
        $this->assertEquals($topic, substr($content, 4, strlen($topic)));
        $this->assertEquals(1, ord($content[4 + strlen($topic)]));
    }
}
