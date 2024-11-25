<?php

use Amp\ByteStream;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Request;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\Router;
use Amp\Http\Server\SocketHttpServer;
use Amp\Log\ConsoleFormatter;
use Amp\Log\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Aksa\RestErrorHandler;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Aksa;
use Aksa\ModelHandler;
use Aksa\RestResponseHandler;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/App/require_all.php';
use function Amp\trapSignal;


$config = parse_ini_file('config.ini');

$str = "host=" . $config['host']
  . " password=" . $config['password']
  . " user=" . $config['username']
  . " db=" . $config['db'];

$postgresConfig = PostgresConfig::fromString($str);
$pool = new PostgresConnectionPool($postgresConfig);

$logHandler = new StreamHandler(ByteStream\getStdout());
$logHandler->pushProcessor(new PsrLogMessageProcessor());
$logHandler->setFormatter(new ConsoleFormatter());

$logger = new Logger('AKSA-server');
$logger->pushHandler($logHandler);
$errorHandler = new RestErrorHandler();
$server = SocketHttpServer::createForDirectAccess($logger);


$router = new Router($server, $logger, $errorHandler);

$agentModel = new ModelHandler(
    $pool,
    $errorHandler,
    "AGENT",
    $schema = [
      'address' => 'STRING',
      'username' => 'STRING',
      'pub_keys' => 'STRING',
      'password' => 'STRING',
      ],
    $privateFields = [
      'private_key' => 'STRING',
      'user_id' => 'INT'
    ]
);

$fileModel = new ModelHandler(
    $pool,
    $errorHandler,
    "FILE",
    $schema = ['link' => "STRING"]
);

$buildModel = new ModelHandler(
    $pool,
    $errorHandler,
    "BUILD",
    $schema = [
      'name' => 'STRING',
      'deploy_script' => 'STRING',
      'run_script' => 'STRING',
      'files' => $fileModel->manyRel(),
    ]
);





$agentView = new RestResponseHandler($logger, $agentModel);

$agentView->setRoutes($router, '/agent/');
$server->expose($config['ip']);
$server->start($router, $errorHandler);

// Serve requests until SIGINT or SIGTERM is received by the process.
Amp\trapSignal([SIGINT, SIGTERM]);

  $server->stop();
