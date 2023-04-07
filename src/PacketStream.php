<?php

declare(strict_types=1);

namespace Tsiura\MqttClient;

use Tsiura\MqttClient\Packets\MQTTFrame;

class PacketStream
{
    private string $buffer;
    public int $pos = 0;

    /**
     * PacketStream constructor.
     * @param string $buffer
     */
    public function __construct(string $buffer = '')
    {
        $this->buffer = $buffer;
    }

    public function clear(): self
    {
        $this->buffer = '';
        $this->pos = 0;
        return $this;
    }

    public function writeByte($byte): self
    {
        $this->buffer .= chr($byte);
        return $this;
    }

    public function writeWord($value): self
    {
        $this->buffer .= pack('n', $value);
        return $this;
    }

    public function writeString(string $value): self
    {
        $this->buffer .= $value;
        return $this;
    }

    public function readByte(): ?int
    {
        return $this->available()
            ? ord($this->buffer[$this->pos++])
            : null;
    }

    public function readWord(): ?int
    {
        if (!$this->available(2)) {
            return null;
        }

        $value = unpack('n', substr($this->buffer, $this->pos, 2))[1];
        $this->pos += 2;

        return $value;
    }

    public function readString(int $length): string
    {
        if (!$this->available($length)) {
            return '';
        }

        $value = substr($this->buffer, $this->pos, $length);
        $this->pos += ($length);
        return $value;
    }

    public function readToEnd(): string
    {
        $value = substr($this->buffer, $this->pos);
        $this->pos = $this->getLength() - 1;
        return $value;
    }

    public function seek(int $pos): self
    {
        $this->pos = max(0, $pos);
        return $this;
    }

    public function getLength(): int
    {
        return strlen($this->buffer);
    }

    public function cut(): self
    {
        $this->buffer = substr($this->buffer, $this->pos);
        $this->pos = 0;

        return $this;
    }

    public function writeRemainingLength(int $remainingLength): self
    {
        $this->buffer .= self::encodeRemainingLength($remainingLength);
        return $this;
    }

    public function readRemainingLength(): ?int
    {
        $multiplier = 1;
        $val = 0;
        $pos = $this->pos;
        do {
            $encodedByte = ord($this->buffer[$pos++]);
            $val += ($encodedByte & 127) * $multiplier;
            $multiplier *= 128;
            if ($multiplier > 128 * 128 * 128) {
                return null;
            }
        } while (($encodedByte & 128) !== 0);
        $this->pos = $pos;

        return $val;
    }

    public function readFrame(): ?MQTTFrame
    {
        $pos = $this->pos;

        $control = $this->readByte();
        $length = $this->readRemainingLength();
        if (null === $control || null === $length) {
            $this->seek($pos);
            return null;
        }

        $content = $this->readString($length);
        $this->cut();

        return new MQTTFrame($control, $length, $content);
    }

    public function append(PacketStream $buffer): self
    {
        $this->buffer .= $buffer->getContent();

        return $this;
    }

    public function getContent(): string
    {
        return $this->buffer;
    }

    public function available(int $size = 1): bool
    {
        return $this->pos + $size <= $this->getLength();
    }

    protected static function getRemainingLengthBytesCount(int $length): int
    {
        return strlen(self::encodeRemainingLength($length));
    }

    protected static function encodeRemainingLength(int $length): string
    {
        $result = '';
        do {
            $encodedByte = $length % 128;
            $length = (int)($length / 128);
            if ($length > 0) {
                $encodedByte |= 128;
            }
            $result .= chr($encodedByte);
        } while ($length > 0);

        return $result;
    }
}
