<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Stringable;

interface PacketInterface extends Stringable
{
    public const PACKET_CONNECT = 1;
    public const PACKET_CONNACK = 2;
    public const PACKET_PUBLISH = 3;
    public const PACKET_PUBACK = 4;
    public const PACKET_PUBREC = 5;
    public const PACKET_PUBREL = 6;
    public const PACKET_PUBCOMP = 7;
    public const PACKET_SUBSCRIBE = 8;
    public const PACKET_SUBACK = 9;
    public const PACKET_UNSUBSCRIBE = 10;
    public const PACKET_UNSUBACK = 11;
    public const PACKET_PINGREQ = 12;
    public const PACKET_PINGRESP = 13;
    public const PACKET_DISCONNECT = 14;
    public const PACKET_AUTH = 15;

    public const QOS_AT_MOST_ONCE = 0;
    public const QOS_AT_LEAST_ONCE = 1;
    public const QOS_EXACTLY_ONCE = 2;

    public static function fromFrame(MQTTFrame $frame): PacketInterface;

    public static function getPacketType(): int;

    public function toFrame(): MQTTFrame;
}
