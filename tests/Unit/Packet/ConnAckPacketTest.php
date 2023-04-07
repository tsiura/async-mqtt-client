<?php

namespace Tests\Unit\Packet;

use Tsiura\MqttClient\Packets\ConnAckPacket;
use PHPUnit\Framework\TestCase;

class ConnAckPacketTest extends TestCase
{
    public function testEncodeDecode()
    {
        $packet = new ConnAckPacket(0, false);

        $content = $packet->toFrame()->content;

        $this->assertEquals(2, strlen($content));
        $this->assertEquals(0, ord($content[0]));
        $this->assertEquals(0, ord($content[1]));

        $this->assertEquals($content, ConnAckPacket::fromFrame($packet->toFrame())->toFrame()->content);
    }
}
