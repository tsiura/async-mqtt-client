<?php

namespace Tests\Unit\Packet;

use Tsiura\MqttClient\Packets\ConnectPacket;
use Tsiura\MqttClient\Packets\PacketInterface;
use PHPUnit\Framework\TestCase;

class ConnectPacketTest extends TestCase
{
    public function testEmptyPacket(): void
    {
        $packet = new ConnectPacket('MQTT', null, null, 'test');
        $frame = $packet->toFrame();

        self::assertEquals(PacketInterface::PACKET_CONNECT, $packet->toFrame()->control >> 4);
        self::assertEquals(16, strlen($frame->content));
        self::assertEquals(16, $frame->remainingLength);
        self::assertEquals(18, strlen($frame->encode()));
    }

    public function testPacketUserOnly(): void
    {
        $packet = new ConnectPacket('MQTT', 'user', null, 'test');
        $frame = $packet->toFrame();

        self::assertEquals(22, strlen($frame->content));
        self::assertEquals(22, $frame->remainingLength);
        self::assertEquals(24, strlen($frame->encode()));
    }

    public function testPacketUserAndPassword(): void
    {
        $packet = new ConnectPacket('MQTT', 'user', 'pass', 'test');
        $frame = $packet->toFrame();

        self::assertEquals(28, strlen($frame->content));
        self::assertEquals(28, $frame->remainingLength);
        self::assertEquals(30, strlen($frame->encode()));
    }

    public function testPacketUserAndPasswordAndWill(): void
    {
        $packet = new ConnectPacket('MQTT', 'user', 'pass', 'test', true, 'will_topic', 'will');
        $frame = $packet->toFrame();

        self::assertEquals(46, strlen($frame->content));
        self::assertEquals(46, $frame->remainingLength);
        self::assertEquals(48, strlen($frame->encode()));
    }

    public function testEncodeDecode()
    {
        $packet = new ConnectPacket('MQTT', 'test1', 'test2', 'test3');

        $this->assertEquals(33, strlen($packet->toFrame()->encode()));
        $this->assertEquals(31, $packet->toFrame()->remainingLength);

        $content = $packet->toFrame()->content;

        $this->assertEquals(0x10, $packet->toFrame()->control);

        $this->assertEquals(0x00, ord($content[0]));
        $this->assertEquals(0x04, ord($content[1]));
        $this->assertEquals('MQTT', substr($content, 2, 4)); // name
        $this->assertEquals(0x04, ord($content[6])); // version
        $this->assertEquals(0xC2, ord($content[7])); // conn flag
        $this->assertEquals(0x00, ord($content[8])); // keep alive
        $this->assertEquals(0x00, ord($content[9])); // keep alive

        $this->assertEquals(0x00, ord($content[10])); // client id len msb
        $this->assertEquals(0x05, ord($content[11])); // client id len lsb
        $this->assertEquals('test3', substr($content, 12, 5)); // client id

        $this->assertEquals(0x00, ord($content[17])); // client id len msb
        $this->assertEquals(0x05, ord($content[18])); // client id len lsb
        $this->assertEquals('test1', substr($content, 19, 5)); // username

        $this->assertEquals(0x00, ord($content[24])); // client id len msb
        $this->assertEquals(0x05, ord($content[25])); // client id len lsb
        $this->assertEquals('test2', substr($content, 26, 5)); // password

        $this->assertEquals($content, ConnectPacket::fromFrame($packet->toFrame())->toFrame()->content);
    }
}
