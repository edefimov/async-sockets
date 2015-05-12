Async sockets library
=====================

[![Build Status][master-travis-image]][master-travis-url] 
[![Coverage Status][master-coverall-image]][master-coverall-url]
[![SensioLabsInsight][master-sensiolabs-image]][master-sensiolabs-url]
[![Dependency Status][master-versioneye-image]][master-versioneye-url]

Async sockets is the library for asynchronous work with sockets based on php streams. Library's workflow process is built on event model, but also select like and common syncronous models are supported.

## Features

- multiple requests execution at once
- dynamically adding new request during working process
- timeouts handling during connection and IO operations 
- separate timeout values for each socket
- custom sockets setup by php stream contexts
- error handling is based on exceptions
- sends notification about socket events to the system 

## Installation

The recommended way to install async sockets library is through composer

```
$ composer require 'edefimov/async-sockets':dev-master
```

[master-travis-image]: https://img.shields.io/travis/edefimov/async-sockets/master.svg?style=flat
[master-travis-url]: https://travis-ci.org/edefimov/async-sockets
[master-coverall-image]: https://img.shields.io/coveralls/edefimov/async-sockets/master.svg?style=flat
[master-coverall-url]: https://coveralls.io/r/edefimov/async-sockets
[master-sensiolabs-image]: https://img.shields.io/sensiolabs/i/c816a980-e97a-46ae-b334-16c6bfd1ec4a.svg?style=flat
[master-sensiolabs-url]: https://insight.sensiolabs.com/projects/c816a980-e97a-46ae-b334-16c6bfd1ec4a
[master-versioneye-image]: https://www.versioneye.com/user/projects/55525b5706c318305500014b/badge.png?style=flat
[master-versioneye-url]: https://www.versioneye.com/user/projects/55525b5706c318305500014b

