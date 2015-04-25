# Async sockets library
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
$ composer require 'edefimov/async-sockets':0.1.*
```
