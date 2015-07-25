Async sockets library
=====================

[![Build Status](https://img.shields.io/travis/edefimov/async-sockets/master.svg?style=flat)](https://travis-ci.org/edefimov/async-sockets)
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

## What is it for
Async sockets library provides networking layer for applications, hides complexity of I/O operations, 
 and cares about connections management. Library will be a powerful solution for such tasks like executing multiple 
 requests at once as well as executing single one. Running multiple requests at once decreases delay of I/O operation 
 to the size of timeout assigned to the slowest server.
 
## Installation

The recommended way to install async sockets library is through composer

stable version:
```
$ composer require edefimov/async-sockets:~0.2.0 --prefer-dist|--prefer-source
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

## Workflow
### Socket types
Async socket library provides two types of sockets - client and server. The recommended way to create 
sockets of different types in source code is to use `AsyncSocketFactory`
```php
$factory = new AsyncSocketFactory();

$client = $factory->createSocket(AsyncSocketFactory::SOCKET_CLIENT);
$server = $factory->createSocket(AsyncSocketFactory::SOCKET_SERVER);
```

### RequestExecutor
RequestExecutor is an engine, which hides all I/O operations and provides event system for client code.
Creating RequestExecutor is as simple as creating sockets:
```php
$executor = $factory->createRequestExecutor();
```

At this point we should decide whether or not to use global event handler or limitation solver.
 
Global event handler is the implementation of `EventHandlerInterface`, which will be called for every event on every
added socket. There are four implementations of this interface out of box:
 - `CallbackEventHandler` takes array of callable, indexed by event type. For certain event type a certain 
   callable will be invoked. Several callbacks can be defined for one event type
 - `EventHandlerFromSymfonyEventDispatcher` dispatches all socket event to symfony [EventDispatcher](http://symfony.com/doc/current/components/event_dispatcher/introduction.html)
 - `EventMultiHandler` is the composite of EventHandlerInterface implementations
 - `RemoveFinishedSocketsEventHandler` decorator for any implementation of `EventHandlerInterface` which automatically
        removes completed sockets from `RequestExecutor`. Recommended to use for accepted clients from server sockets.

The limitation solver is the component restricts amount of executed at once requests. Out of the box two strategies
are available:
 - `NoLimitationSolver` doesn't restrict anything, it is default one
 - `ConstantLimitationSolver` restricts amount of running requests to given number
Custom limitation solver can be written by implementing `LimitationSolverInterface`. If you need an access to socket
events from the solver, just implement `EventHandlerInterface` in addition to the first one.

To set up event handler or limitation solver use this snippet
```php
$executor->withEventHandler(
    new CallbackEventHandler(
        [
            EventType::INITIALIZE => [$this, 'onInitialize'],
            EventType::WRITE      => [$this, 'onWrite'],
            ....
        ]
    )
);

$executor->withLimitationSolver(new ConstantLimitationSolver(20));
```

### Event types
To deal with sockets you need to subscribe to events you are interested in. There are several type of events:
 - EventType::INITIALIZE is the first event sent for socket and can be used for some preparations like setting 
        destination address for socket
 - EventType::CONNECTED - socket has been just connected to server
 - EventType::ACCEPT - applicable only for server sockets, fires each time when there is new client, no matter what
        kind of transport is used tcp, udp, unix or something else. Client socket can be got from `AcceptEvent`
 - EventType::READ - new frame has been arrived. Frame can be extracted from `ReadEvent` object, which will be passed
        to callback function. Applicable only for client sockets
 - EventType::WRITE - socket is ready to write data. New data must be passed to socket through `WriteEvent` object
 - EventType::DISCONNECTED - connection to remote server is now closed. This event won't be fired, if socket hasn't connected
 - EventType::FINALIZE - socket I/O cycle is complete and socket should be removed from RequestExecutor
 - EventType::TIMEOUT - socket failed to connect/read/write data during set up period of time
 - EventType::EXCEPTION - some `NetworkSocketException` occurred, detailed information can be retrieved from `SocketExceptionEvent`

Each event type has `Event` object (or one of its children) as the callback argument. If you have installed symfony event
 dispatcher component, library's `Event` object will be inherited from symfony `Event` object.
 

### Adding sockets
To add socket to RequestExecutor use `SocketBagInterface` returned by `socketBag` method of `RequestExecutor`
```php
$executor->socketBag()->addSocket(
    $socket, 
    new WriteOperation('some data'),  // or new ReadOperation
    [
        RequestExecutorInterface::META_ADDRESS            => 'tls://github.com:443',
        RequestExecutorInterface::META_CONNECTION_TIMEOUT => 30,
        RequestExecutorInterface::META_IO_TIMEOUT         => 5,
    ],
    $handler
);
```

Function `addSocket` accepts four arguments: socket, operation, metadata and event handler. 
Socket is the object, created by `AsyncSocketFactory` or received by `AcceptEvent`.
Operation can be one of `ReadOperation` or `WriteOperation` classes.
Metadata is key-value array with settings for this socket and will be described later.
Event handler is implementation of `EventHandlerInterface`, which will be invoked only for this socket.

#### Determination of frame boundaries
When `ReadOperation` is applied to socket it is possible to determine frame boundaries when receiving data structure 
is known. To achieve this purpose helps `FramePickerInterface`. It gives hints to socket engine where the end of frame 
is and whether it is reached. These implementations of `FramePickerInterface` are available out of the box:
 - `NullFramePicker` - default implementation is used if nothing else is provided, reads data until network
                       connection is active. *WARNING*: It is strongly recommended to avoid this type of picker in
                       production code and always use any other one.
 - `FixedLengthFramePicker` - frame of predefined length, when length bytes are received immediately fires READ event.
 - `MarkerFramePicker` - frame of variable length, but at start and end marker, or at least end marker, are known.
 - `RawFramePicker` - raw frame with chunk of data just received from network read call.
 
To write own frame picker just implement `FramePickerInterface`.
```php
$read = new ReadOperation(new FixedLengthFramePicker(256)); // read 256 bytes from response

$read = new MarkerFramePicker('HTTP', "\r\n\r\n"); // get HTTP headers from response

$read = new MarkerFramePicker(null, "\x00"); // read data until 0-byte
```

If end of frame is not reached and transfer terminates, then EXCEPTION event will be fired with `FrameSocketException`
type inside of event.

#### Metadata
Metadata is settings for all operations on given socket. Supported keys are defined in `RequestExecutorInterface`.
For now these keys are supported:
  - META_ADDRESS - string in form scheme://target, destination address for client socket and local address for server.
        This value is required for created sockets and can be ignored for accepted ones.
  - META_CONNECTION_TIMEOUT - int, value in seconds, if there was no connection during this period, socket would 
        be closed automatically and TIMEOUT event will be fired. If value is omitted then php.ini setting default_socket_timeout is used
  - META_IO_TIMEOUT - float, seconds with microseconds, if there were no data sent/received during this period of time,
        then TIMEOUT event will be fired
  - META_USER_CONTEXT - mixed, any user defined data, doesn't use somehow by engine
  - META_SOCKET_STREAM_CONTEXT - array or resource - any valid stream context created by stream_context_create function 
        or null or array with options. If array value is used, then it should contain two nested keys:
        "options" and "params", which will be passed to stream_context_create parameters respectively.
  - META_REQUEST_COMPLETE - bool, read-only, flag indicating that execute operation on this socket is complete
  - META_CONNECTION_START_TIME - float, read-only, int part is seconds and float is microseconds, time 
        when connection process has begun. If connection process hasn't started yet, the value will be null
  - META_CONNECTION_FINISH_TIME - float, read-only, int part is seconds and float is microseconds, time when 
        connection process has ended. If connection process hasn't finished yet, the value will be null
  - META_LAST_IO_START_TIME - float, read-only, int part is seconds and float is microseconds, time when last 
        io operation has started

### Starting request
After setting up event handler and adding at least one socket request can be executed by calling
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
See full example [here](https://github.com/edefimov/async-sockets/blob/master/demos/Demo/RequestExecutorClient.php)

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
See full example [here](https://github.com/edefimov/async-sockets/blob/master/demos/Demo/SimpleServer.php)
