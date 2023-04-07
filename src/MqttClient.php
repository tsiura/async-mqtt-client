<?php

declare(strict_types=1);

namespace Tsiura\MqttClient;

use Tsiura\MqttClient\Exception\MqttClientException;
use Tsiura\MqttClient\Exception\MqttPacketException;
use Tsiura\MqttClient\Packets\ConnAckPacket;
use Tsiura\MqttClient\Packets\ConnectPacket;
use Tsiura\MqttClient\Packets\DisconnectPacket;
use Tsiura\MqttClient\Packets\MQTTFrame;
use Tsiura\MqttClient\Packets\PacketInterface;
use Tsiura\MqttClient\Packets\PingReqPacket;
use Tsiura\MqttClient\Packets\PingRespPacket;
use Tsiura\MqttClient\Packets\PubAckPacket;
use Tsiura\MqttClient\Packets\PubCompPacket;
use Tsiura\MqttClient\Packets\PublishPacket;
use Tsiura\MqttClient\Packets\PubRecPacket;
use Tsiura\MqttClient\Packets\PubRelPacket;
use Tsiura\MqttClient\Packets\SubAckPacket;
use Tsiura\MqttClient\Packets\SubscribePacket;
use Tsiura\MqttClient\Packets\UnsubAckPacket;
use Tsiura\MqttClient\Packets\UnsubscribePacket;
use Tsiura\MqttClient\Watcher\ExpressionObject;
use Tsiura\PromiseWatcher\ObjectWatcher;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Promise\PromiseInterface;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use React\Socket\ConnectorInterface;
use function React\Promise\resolve;

class MqttClient
{
    use LoggerAwareTrait;

    public const DEFAULT_WATCHING_TIMEOUT = 60;

    private ?ConnectionInterface $connection;
    private ConnectorInterface $connector;
    private PacketStream $stream;

    private array $subscriptions = [];
    private int $packetId = 1;
    private ?TimerInterface $pingTimer = null;

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly ConnectionOptions $options,
        private readonly ObjectWatcher $watcher,
    ) {
        $this->stream = new PacketStream();
        $this->logger = new NullLogger();
        $this->connector = new Connector([], $this->loop);
    }

    /**
     * Promise resolves after client received ConnAckPacket
     * @return PromiseInterface
     */
    public function connect(): PromiseInterface
    {
        return $this->connector->connect($this->options->uri)
            ->then(function (ConnectionInterface $connection) {
                $this->connection = $connection;
                $this->initHandlers();
                $this->initPing();
                $packet = new ConnectPacket('MQTT', $this->options->username, $this->options->password, $this->options->clientId);
                $watching = $this->watcher->createWatching(new ExpressionObject(ConnAckPacket::class), self::DEFAULT_WATCHING_TIMEOUT);
                if (!$this->sendPacket($packet)) {
                    $watching->cancel();
                    throw new MqttClientException('Error sending CONNECT');
                }

                return $watching->start()
                    ->then(function (ConnAckPacket $conAck) {
                        if ($conAck->retCode !== 0) {
                            throw new MqttClientException(sprintf('Connection refused with error code %d', $conAck->retCode));
                        }

                        return $conAck->retCode;
                    });
            });
    }

    public function isConnected(): bool
    {
        return null !== $this->connection && $this->connection->isReadable() && $this->connection->isWritable();
    }

    public function disconnect(): void
    {
        $this->watcher->clear();
        if ($this->pingTimer) {
            $this->loop->cancelTimer($this->pingTimer);
            $this->pingTimer = null;
        }
        if ($this->isConnected()) {
            $this->sendPacket(new DisconnectPacket());
            $this->connection->close();
        }
        $this->connection = null;
    }

    /**
     * Promise resolves after client received PingRespPacket
     * @return PromiseInterface
     * @throws MqttClientException
     */
    public function ping(): PromiseInterface
    {
        $packet = new PingReqPacket();
        $watching = $this->watcher->createWatching(new ExpressionObject(PingRespPacket::class), self::DEFAULT_WATCHING_TIMEOUT);
        if (!$this->sendPacket($packet)) {
            $watching->cancel();
            throw new MqttClientException('Error sending PING');
        }

        return $watching->start();
    }

    /**
     * Promise resolves after client received SubAckPacket
     * @param string $topic
     * @param callable $callback
     * @param int $qos
     * @param int|null $id
     * @return PromiseInterface
     * @throws MqttClientException
     * @throws MqttPacketException
     */
    public function subscribe(string $topic, callable $callback, int $qos = PacketInterface::QOS_AT_MOST_ONCE, ?int $id = null): PromiseInterface
    {
        if (isset($this->subscriptions[$topic])) {
            throw new MqttClientException(sprintf('Subscription for topic [%s] already exist', $topic));
        }

        $packetId = $id ?? $this->getNextId();
        $packet = new SubscribePacket($packetId);
        $packet->addTopic($topic, $qos);

        $watching = $this->watcher->createWatching(new ExpressionObject(SubAckPacket::class, ['packetId' => $packetId]), self::DEFAULT_WATCHING_TIMEOUT);

        if (!$this->sendPacket($packet)) {
            $watching->cancel();
            throw new MqttClientException('Error sending SUBSCRIBE');
        }

        return $watching->start()
            ->then(function (SubAckPacket $subAck) use ($topic, $callback) {
                if ($subAck->retCodes[0] === 0x80) {
                    throw new MqttClientException(sprintf('Failed to subscribe for topic [%s]', $topic));
                }
                $this->subscriptions[$topic] = $callback;

                return $subAck->retCodes;
            });
    }

    /**
     * Promise resolves after client received UnsubAckPacket
     * @param string $topic
     * @return PromiseInterface
     * @throws MqttClientException
     */
    public function unsubscribe(string $topic): PromiseInterface
    {
        $packetId = $this->getNextId();
        $packet = new UnsubscribePacket($packetId);
        $packet->addTopic($topic);

        $watching = $this->watcher->createWatching(new ExpressionObject(UnsubAckPacket::class, ['packetId' => $packetId]), self::DEFAULT_WATCHING_TIMEOUT);
        if (!$this->sendPacket($packet)) {
            $watching->cancel();
            throw new MqttClientException('Error sending SUBSCRIBE');
        }

        return $watching->start();
    }

    /**
     * Promise resolves after client received all mandatory packets from server (depends on QoS)
     * @param string $topic
     * @param string $message
     * @param int $qos
     * @param bool $dup
     * @param bool $retain
     * @param int|null $packetId
     * @return PromiseInterface
     * @throws MqttClientException
     */
    public function publish(string $topic, string $message = '', int $qos = 0, bool $dup = false, bool $retain = false, ?int $packetId = null): PromiseInterface
    {
        $packet = new PublishPacket($topic, $message, $qos, $dup, $retain);
        if ($qos > PacketInterface::QOS_AT_MOST_ONCE) {
            $packetId = $packetId ?? $this->getNextId();
            $packet->packetId = $packetId;
        }

        $watching = null;
        if ($qos === PacketInterface::QOS_AT_LEAST_ONCE) {
            $watching = $this->watcher->createWatching(new ExpressionObject(PubAckPacket::class, ['packetId' => $packetId]), self::DEFAULT_WATCHING_TIMEOUT);
        } elseif ($qos === PacketInterface::QOS_EXACTLY_ONCE) {
            $watching = $this->watcher->createWatching(new ExpressionObject(PubRecPacket::class, ['packetId' => $packetId]), self::DEFAULT_WATCHING_TIMEOUT);
        }

        if (!$this->sendPacket($packet)) {
            $watching?->cancel();
            throw new MqttClientException('Error sending SUBSCRIBE');
        }

        if ($qos === PacketInterface::QOS_AT_MOST_ONCE) {
            return resolve();
        }

        $promise = $watching->start();

        if ($qos === PacketInterface::QOS_AT_LEAST_ONCE) {
            return $promise;
        }

        return $promise->then(function () use ($packetId) {
            $packet = new PubRelPacket($packetId);
            $watching = $this->watcher->createWatching(new ExpressionObject(PubCompPacket::class, ['packetId' => $packetId]), self::DEFAULT_WATCHING_TIMEOUT);
            if (!$this->sendPacket($packet)) {
                $watching->cancel();
                throw new MqttClientException('Error sending PubCompPacket');
            }

            return $watching->start();
        });
    }

    public function sendPacket(PacketInterface $packet): bool
    {
        $this->logger->debug(sprintf('Sending packet >> %s', $packet));

        return $this->connection->write($packet->toFrame()->encode());
    }

    private function initHandlers(): void
    {
        $this->connection->on('data', fn($data) => $this->handleData($data));
        $this->connection->on('error', fn() => $this->handleError());
        $this->connection->on('end', fn() => $this->handleEnd());
        $this->connection->on('close', fn() => $this->handleClose());
    }

    private function handleError(): void
    {
        $this->logger->warning('handleError');
        $this->disconnect();
    }

    private function handleEnd(): void
    {
        $this->logger->warning('handleEnd');
        $this->disconnect();
    }

    private function handleClose(): void
    {
        $this->logger->warning('handleClose');
        $this->disconnect();
    }

    private function handleData(string $data): void
    {
        try {
            $this->stream->writeString($data);
            $frame = $this->stream->readFrame();

            if (null !== $frame) {
                $this->handleFrame($frame);
            }
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage(), $e->getTrace());
        }
    }

    private function handleFrame(MQTTFrame $frame): void
    {
        $available = [
            ConnectPacket::class => null,
            ConnAckPacket::class => null,
            PublishPacket::class => 'handlePublish',
            PubAckPacket::class => null,
            PubRecPacket::class => null,
            PubRelPacket::class => null,
            PubCompPacket::class => null,
            SubscribePacket::class => null,
            SubAckPacket::class => null,
            UnsubscribePacket::class => null,
            UnsubAckPacket::class => null,
            PingReqPacket::class => null,
            PingRespPacket::class => null,
            DisconnectPacket::class => 'disconnect',
        ];
        $type = $frame->control >> 4;
        $packet = $handler = null;
        /** @var PacketInterface|string $class */
        foreach ($available as $class => $method) {
            if ($class::getPacketType() === $type) {
                $packet = $class::fromFrame($frame);
                $handler = $method;
            }
        }
        if (null === $packet) {
            throw new MqttPacketException(sprintf('Unsupported packet type [%d]', $type));
        }

        $this->logger->debug(sprintf('handleFrame > %s', $packet));

        $this->watcher->evaluate($packet);

        if (null !== $handler) {
            $this->$handler($packet);
        }
    }

    private function handlePublish(PublishPacket $packet): void
    {
        $topic = $packet->topic;

        if ($packet->qos === PacketInterface::QOS_AT_LEAST_ONCE) {
            $this->sendPacket(new PubAckPacket($packet->packetId));
        } elseif ($packet->qos === PacketInterface::QOS_EXACTLY_ONCE) {
            $watching = $this->watcher->createWatching(new ExpressionObject(PubRelPacket::class, ['packetId' => $packet->packetId]), self::DEFAULT_WATCHING_TIMEOUT);
            $this->sendPacket(new PubRecPacket($packet->packetId));
            $watching->start()->then(fn () => $this->sendPacket(new PubCompPacket($packet->packetId)));
        }

        foreach ($this->subscriptions as $subscription => $callback) {
            if (false !== Utils::checkMatch($topic, $subscription)) {
                $callback($packet->message, $packet->topic, $packet->packetId, $packet->qos, $packet->dup);
            }
        }
    }

    private function initPing(): void
    {
        $this->loop->addPeriodicTimer(60, fn () => $this->ping());
    }

    private function getNextId(): int
    {
        if ($this->packetId >= 65535) {
            $this->packetId = 1;
        }
        return $this->packetId++;
    }
}
