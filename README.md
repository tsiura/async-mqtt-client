# async-mqtt-client

### Basic usage example. Using `await` function optional, but useful
```
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Tsiura\MqttClient\Packets\PacketInterface;
use React\EventLoop\Loop;
use Tsiura\MqttClient\ConnectionOptions;
use Tsiura\MqttClient\MqttClient;
use Tsiura\PromiseWatcher\ObjectWatcher;
use function React\Async\await;

$uri = '';
$username = '';
$password = '';
$clientId = 'reactphp-mqtt';

$loop = Loop::get();

$formatter = new LineFormatter(
    "[%datetime%] %channel%  %level_name%  %message%\t%context%\t%extra%" . PHP_EOL,
    'Y-m-d H:i:s.v',
    true,
    true
);

$handler = new StreamHandler('php://output', Level::Debug);
$handler->setFormatter($formatter);
$logger = new Logger('MQTT', [$handler]);

$options = new ConnectionOptions($uri, $username, $password, $clientId);
$client = new MqttClient($loop, $options, new ObjectWatcher($loop));
$client->setLogger($logger);

await($client->connect());

await($client->subscribe('#', function (string $data, string $topic) {
    echo sprintf('Received data [%s] for topic [%s]', $data, $topic) . PHP_EOL;
}, PacketInterface::QOS_AT_LEAST_ONCE));

$client->publish('/test', 'Hello mqtt!', PacketInterface::QOS_AT_LEAST_ONCE);

$loop->run();
```