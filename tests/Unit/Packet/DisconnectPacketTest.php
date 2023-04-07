<?php

namespace Tests\Unit\Packet;

use Tsiura\MqttClient\Packets\DisconnectPacket;
use PHPUnit\Framework\TestCase;

class DisconnectPacketTest extends TestCase
{
    public function testEncodeDecode()
    {
        $packet = new DisconnectPacket();

        $content = $packet->toFrame()->content;

        $this->assertEquals($content, DisconnectPacket::fromFrame($packet->toFrame())->toFrame()->content);
    }
}
