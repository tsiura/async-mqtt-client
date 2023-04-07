<?php

declare(strict_types=1);

namespace Tsiura\MqttClient\Packets;

use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\PacketStream;
use ReflectionClass;

class ConnectPacket extends AbstractPacket
{
    public function __construct(
        private readonly string $version,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private ?string $clientId = null,
        private readonly bool $cleanSession = true,
        private readonly ?string $willTopic = null,
        private readonly ?string $willMessage = null,
        private readonly bool $willQos = false,
        private readonly int $willRetain = 0,
        private readonly int $keepAlive = 0
    ) {
    }

    public static function getPacketType(): int
    {
        return self::PACKET_CONNECT;
    }

    /**
     * @param MQTTFrame $frame
     * @return PacketInterface
     * @throws MqttPacketException
     */
    public static function fromFrame(MQTTFrame $frame): PacketInterface
    {
        parent::validatePacketType($frame->control);

        $content = new PacketStream($frame->content);

        $protoVerLen = $content->readWord();
        $protoVersion = $content->readString($protoVerLen);
        // $protoLevel
        $content->readByte();

        $flags = $content->readByte();
        $unameExist = ($flags & (1 << 7)) > 0;
        $pwdExist = ($flags & (1 << 6)) > 0;
        $willRet = ($flags & (1 << 5)) > 0;
        $willQos = ($flags >> 3) & 3;
        $willFlag = ($flags & (1 << 2)) > 0;
        $cleanSess = ($flags & (1 << 1)) > 0;

        $keepAlive = $content->readWord();

        $cIdLen = $content->readWord();
        $cId = $content->readString($cIdLen);

        if ($willFlag) {
            $willTopicLen = $content->readWord();
            $willTopic = $content->readString($willTopicLen);

            $willMsgLen = $content->readWord();
            $willMsg = $content->readString($willMsgLen);
        }
        if ($unameExist) {
            $unameLen = $content->readWord();
            $username = $content->readString($unameLen);
        }
        if ($pwdExist) {
            $pwdLen = $content->readWord();
            $password = $content->readString($pwdLen);
        }

        return new self(
            $protoVersion,
            $username ?? null,
            $password ?? null,
            $cId,
            $cleanSess,
            $willTopic ?? null,
            $willMsg ?? null,
            (bool)$willQos,
            (int)$willRet,
            $keepAlive
        );
    }

    public function toFrame(): MQTTFrame
    {
        $content = new PacketStream();
        $content->writeWord(strlen($this->version));
        $content->writeString($this->version);
        $content->writeByte(4);
        $content->writeByte($this->getConnectionFlags());
        $content->writeWord($this->keepAlive);
        $content->writeWord(strlen($this->getClientId()));
        $content->writeString($this->getClientId());
        if (!empty($this->username)) {
            $content->writeWord(strlen($this->username));
            $content->writeString($this->username);
        }
        if (!empty($this->password)) {
            $content->writeWord(strlen($this->password));
            $content->writeString($this->password);
        }
        if (!empty($this->willTopic) && null !== $this->willMessage) {
            $content->writeWord(strlen($this->willTopic));
            $content->writeString($this->willTopic);
            $content->writeWord(strlen($this->willMessage));
            $content->writeString($this->willMessage);
        }

        return new MQTTFrame(
            self::getPacketType() << 4,
            $content->getLength(),
            $content->getContent()
        );
    }

    public function __toString(): string
    {
        $c = new ReflectionClass($this);
        return sprintf(
            '[%s] version: %s, clientId: %s, username: %s, password: %s, cleanSession: %d, willTopic: %s, willMsg: %s, willQoS: %d, willRetain: %d, keepAlive: %d',
            $c->getShortName(),
            $this->version,
            $this->clientId,
            $this->username,
            $this->password,
            $this->cleanSession,
            $this->willTopic,
            $this->willMessage,
            $this->willQos,
            $this->willRetain,
            $this->keepAlive,
        );
    }

    protected function getConnectionFlags(): int
    {
        $byte = 0;
        if ($this->cleanSession) {
            $byte |= (1 << 1);
        }
        if (!empty($this->willTopic) && null !== $this->willMessage) {
            $byte |= (1 << 2);
        }

        if ($this->willQos) {
            $byte |= (1 << 3);
        }

        if ($this->willRetain) {
            $byte |= (1 << 5);
        }

        if (null !== $this->password) {
            $byte |= (1 << 6);
        }

        if (null !== $this->username) {
            $byte |= (1 << 7);
        }

        return $byte;
    }

    private function getClientId(): string
    {
        if (empty($this->clientId)) {
            $this->clientId = md5(microtime() . mt_rand(0, 999));
        }

        return strlen($this->clientId) > 23
            ? substr($this->clientId, 0, 23)
            : $this->clientId;
    }
}
