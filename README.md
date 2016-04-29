Async sockets library
=====================

[![Build Status](https://img.shields.io/travis/edefimov/async-sockets/master.svg?style=flat)](https://travis-ci.org/edefimov/async-sockets)
[![Documentation Status](https://readthedocs.org/projects/async-sockets/badge/?version=latest)](http://async-sockets.readthedocs.io/en/latest/?badge=latest)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/edefimov/async-sockets.svg?style=flat)](https://scrutinizer-ci.com/g/edefimov/async-sockets/)
[![SensioLabsInsight](https://img.shields.io/sensiolabs/i/c816a980-e97a-46ae-b334-16c6bfd1ec4a.svg?style=flat)](https://insight.sensiolabs.com/projects/c816a980-e97a-46ae-b334-16c6bfd1ec4a)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/edefimov/async-sockets.svg?style=flat)](https://scrutinizer-ci.com/g/edefimov/async-sockets/)
[![GitHub release](https://img.shields.io/github/release/edefimov/async-sockets.svg?style=flat)](https://github.com/edefimov/async-sockets/releases/latest)
[![Dependency Status](https://www.versioneye.com/user/projects/55525b5706c318305500014b/badge.png?style=flat)](https://www.versioneye.com/user/projects/55525b5706c318305500014b)
[![Downloads](https://img.shields.io/packagist/dt/edefimov/async-sockets.svg)](https://packagist.org/packages/edefimov/async-sockets)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.4-777bb4.svg?style=flat)](https://php.net/)

Async sockets is event-based library for asynchronous work with sockets built on php streams.

## Features

 - multiple requests execution at once
 - distinguish frame boundaries
 - server socket support
 - persistent connections support
 - multiple persistent connections to the same host:port
 - processing TLS handshake asynchronous
 - synchronization between sockets
 - determine datagram size for UDP sockets
 - all transports returned by stream_get_transports are supported
 - compatible with symfony event dispatcher component
 - full control over timeouts
 - dynamically adding new request during execution process
 - separate timeout values for each socket
 - custom sockets setup by php stream contexts
 - custom user context for each socket
 - stop request either for certain socket or for all of them
 - strategies for limiting number of running requests
 - error handling is based on exceptions
 - supports libevent engine

## What is it for
Async sockets library provides networking layer for applications, hides complexity of I/O operations, 
 and cares about connections management. The library will be a powerful base for implementing
 arbitrary networking protocol as for implementing server on PHP. 
 Running multiple requests at once decreases delay of I/O operation 
 to the size of timeout assigned to the slowest server.

## Documentation

[Stable version](https://async-sockets.readthedocs.io/en/stable/)

[Latest version](https://async-sockets.readthedocs.io/en/latest/)

## Installation

The recommended way to install async sockets library is through composer

stable version:
```
$ composer require edefimov/async-sockets:~0.3.0 --prefer-dist|--prefer-source
```

actual version:
```
$ composer require edefimov/async-sockets:dev-master
```

Use `--prefer-dist` option in production environment, so as it ignores downloading of test and demo files, 
and `--prefer-source` option for development. Development version includes both test and demo files.

## Quick start
#### Step 1. Create `AsyncSocketFactory` at point where you want to start request
```php
$factory = new AsyncSocketFactory();
```

#### Step 2. Create RequestExecutor and proper amount of sockets
```php
$client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
$anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

$executor = $factory->createRequestExecutor();
```

#### Step 3. Create event handler with events, you are interested in
```php
$handler = new CallbackEventHandler(
    [
        EventType::INITIALIZE   => [$this, 'onInitialize'],
        EventType::CONNECTED    => [$this, 'onConnected'],
        EventType::WRITE        => [$this, 'onWrite'],
        EventType::READ         => [$this, 'onRead'],
        EventType::ACCEPT       => [$this, 'onAccept'],
        EventType::DATA_ALERT   => [$this, 'onDataAlert'],
        EventType::DISCONNECTED => [$this, 'onDisconnected'],
        EventType::FINALIZE     => [$this, 'onFinalize'],
        EventType::EXCEPTION    => [$this, 'onException'],
        EventType::TIMEOUT      => [$this, 'onTimeout'],
    ]
);
```

#### Step 4. Add sockets into RequestExecutor
```php
$executor->socketBag()->addSocket(
    $client, 
    new WriteOperation(), 
    [
        RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
        RequestExecutorInterface::META_CONNECTION_TIMEOUT => 30,
        RequestExecutorInterface::META_IO_TIMEOUT => 5,
    ],
    $handler
);
$executor->socketBag()->addSocket(
    $anotherClient, 
    new WriteOperation(), 
    [
        RequestExecutorInterface::META_ADDRESS => 'tls://packagist.org:443',
        RequestExecutorInterface::META_CONNECTION_TIMEOUT => 10,
        RequestExecutorInterface::META_IO_TIMEOUT => 2,
    ],
    $handler
);
```

#### Step 5. Execute it!
```php
$executor->executeRequest();
```

## Example usage
### Client socket
```php
$factory = new AsyncSocketFactory();

$client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
$anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

$executor = $factory->createRequestExecutor();

$handler = new CallbackEventHandler(
    [
        EventType::INITIALIZE   => [$this, 'onInitialize'],
        EventType::CONNECTED    => [$this, 'onConnected'],
        EventType::WRITE        => [$this, 'onWrite'],
        EventType::READ         => [$this, 'onRead'],
        EventType::DISCONNECTED => [$this, 'onDisconnected'],
        EventType::FINALIZE     => [$this, 'onFinalize'],
        EventType::EXCEPTION    => [$this, 'onException'],
        EventType::TIMEOUT      => [$this, 'onTimeout'],
    ]
);

$executor->socketBag()->addSocket(
    $client, 
    new WriteOperation(), 
    [
        RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
        RequestExecutorInterface::META_CONNECTION_TIMEOUT => 30,
        RequestExecutorInterface::META_IO_TIMEOUT => 5,
    ],
    $handler
);
$executor->socketBag()->addSocket(
    $anotherClient, 
    new WriteOperation(), 
    [
        RequestExecutorInterface::META_ADDRESS => 'tls://packagist.org:443',
        RequestExecutorInterface::META_CONNECTION_TIMEOUT => 10,
        RequestExecutorInterface::META_IO_TIMEOUT => 2,
    ],
    $handler
);

$executor->executeRequest();
```
See full example [here](https://github.com/edefimov/async-sockets/blob/0.3.0/demos/Demo/RequestExecutorClient.php)

### Server socket
```php
$factory       = new AsyncSocketFactory();
$serverSocket  = $factory->createSocket(AsyncSocketFactory::SOCKET_SERVER);
$executor      = $factory->createRequestExecutor();

$executor->socketBag()->addSocket(
    $serverSocket,
    new ReadOperation(),
    [
        RequestExecutorInterface::META_ADDRESS            => "tcp://localhost:10280", // or "udp://localhost:10280"
        RequestExecutorInterface::META_CONNECTION_TIMEOUT => RequestExecutorInterface::WAIT_FOREVER,
        RequestExecutorInterface::META_IO_TIMEOUT         => RequestExecutorInterface::WAIT_FOREVER,
    ],
    new CallbackEventHandler(
        [
            EventType::ACCEPT => function (AcceptEvent $event){
                $event->getExecutor()->socketBag()->addSocket(
                    $event->getClientSocket(),
                    new ReadOperation(),
                    [ ],
                    // client handlers
                );
            }
        ]
    )
);

$executor->executeRequest();
```
See full example [here](https://github.com/edefimov/async-sockets/blob/0.3.0/demos/Demo/SimpleServer.php)
