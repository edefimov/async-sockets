Async sockets library
=====================

[![Build Status](https://img.shields.io/travis/edefimov/async-sockets/master.svg?style=flat)](https://travis-ci.org/edefimov/async-sockets)
[![Scrutinizer Coverage](https://img.shields.io/scrutinizer/coverage/g/edefimov/async-sockets.svg?style=flat)](https://scrutinizer-ci.com/g/edefimov/async-sockets/)
[![SensioLabsInsight](https://img.shields.io/sensiolabs/i/c816a980-e97a-46ae-b334-16c6bfd1ec4a.svg?style=flat)](https://insight.sensiolabs.com/projects/c816a980-e97a-46ae-b334-16c6bfd1ec4a)
[![Scrutinizer](https://img.shields.io/scrutinizer/g/edefimov/async-sockets.svg?style=flat)](https://scrutinizer-ci.com/g/edefimov/async-sockets/)
[![GitHub release](https://img.shields.io/github/release/edefimov/async-sockets.svg?style=flat)](https://github.com/edefimov/async-sockets/releases/latest)
[![Dependency Status](https://www.versioneye.com/user/projects/55525b5706c318305500014b/badge.png?style=flat)](https://www.versioneye.com/user/projects/55525b5706c318305500014b)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.4-777bb4.svg?style=flat)](https://php.net/)

Async sockets is the library for asynchronous work with sockets based on php streams. 
Library's workflow process is built on event model, but also select like and common syncronous models are supported.

## Features

- multiple requests execution at once
- full control over timeouts
- dynamically adding new request during working process
- separate timeout values for each socket
- custom sockets setup by php stream contexts
- custom user context for each socket
- stop request either for certain socket or for all of them
- strategies for limiting number of running requests
- error handling is based on exceptions
- sends notification about socket events to the system

## What is it for
Async sockets library will be good for such tasks like executing multiple requests at once. When you have several
 servers with different delays the response from the fastest ones will be delivered earlier than from the slowest. 
 This allows to have maximum delay at size of timeout assigned for the slowest server.
 
## Installation

The recommended way to install async sockets library is through composer

```
$ composer require edefimov/async-sockets:~0.1.0 --prefer-dist|--prefer-source
```

Use `--prefer-dist` option in production environment, so as it ignores downloading of test and demo files, 
and `--prefer-source` option for development. Development version includes both test and demo files.

## Example usage

#### Step 1. Create AsyncSocketFactory at point where you want to start request
```php
$factory = new AsyncSocketFactory();
```

#### Step 2. Create RequestExecutor and proper amount of sockets
```php
$client        = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
$anotherClient = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);

$executor = $factory->createRequestExecutor();
```

#### Step 3. Add your sockets into RequestExecutor
```php
$executor->addSocket($client, RequestExecutorInterface::OPERATION_WRITE, [
    RequestExecutorInterface::META_ADDRESS => 'tls://github.com:443',
    RequestExecutorInterface::META_CONNECTION_TIMEOUT => 30,
    RequestExecutorInterface::META_IO_TIMEOUT => 5,
]);
$executor->addSocket($anotherClient, RequestExecutorInterface::OPERATION_WRITE, [
    RequestExecutorInterface::META_ADDRESS => 'tls://packagist.org:443',
    RequestExecutorInterface::META_CONNECTION_TIMEOUT => 10,
    RequestExecutorInterface::META_IO_TIMEOUT => 2,
]);
```

#### Step 4. Add handlers for events you are interested in
```php
$executor->addHandler([
    EventType::INITIALIZE   => [$this, 'onInitialize'],
    EventType::CONNECTED    => [$this, 'onConnected'],
    EventType::WRITE        => [$this, 'onWrite'],
    EventType::READ         => [$this, 'onRead'],
    EventType::DISCONNECTED => [$this, 'onDisconnected'],
    EventType::FINALIZE     => [$this, 'onFinalize'],
    EventType::EXCEPTION    => [$this, 'onException'],
    EventType::TIMEOUT      => [$this, 'onTimeout'],
]);
```

#### Step 5. Execute it!
```php
$executor->executeRequest();
```

See full example [here](https://github.com/edefimov/async-sockets/blob/master/demos/Demo/RequestExecutorClient.php)

