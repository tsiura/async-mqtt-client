<?php

namespace Tests\Unit;

use Tsiura\MqttClient\Packets\SubAckPacket;
use Tsiura\MqttClient\PacketStream;
use PHPUnit\Framework\TestCase;

class MqttStreamTest extends TestCase
{
    public function testStreamRemainingLength()
    {
        $str = implode('', array_map(fn($v) => chr($v), [0x90, 0x03, 0x00, 0x08, 0x00]));
        $stream = new PacketStream($str);

        self::assertEquals(SubAckPacket::getPacketType(), $stream->readByte() >> 4);
        self::assertEquals(3, $stream->readRemainingLength());

        $stream->clear();
        $stream->writeRemainingLength(127);
        self::assertEquals(1, $stream->getLength());
        self::assertEquals(127, $stream->readRemainingLength());

        $stream->clear();
        $stream->writeRemainingLength(200);
        self::assertEquals(2, $stream->getLength());
        self::assertEquals(200, $stream->readRemainingLength());
        self::assertEquals(2, $stream->getLength());

        $stream->clear();
        $stream->writeRemainingLength(20000);
        self::assertEquals(3, $stream->getLength());
        self::assertEquals(20000, $stream->readRemainingLength());
        self::assertEquals(3, $stream->getLength());
    }
}
