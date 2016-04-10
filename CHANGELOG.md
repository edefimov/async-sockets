# Changelog

0.3.0 (Apr 10, 2016)
--------------------
### New features:
 - persistent connections support
 - multiple persistent connections to the same host:port
 - synchronization support between sockets
 - added RequestExecutor engine based on [libevent](https://pecl.php.net/package/libevent)
 - processing TLS handshake asynchronous
 - added Configuration support

### Changes:
 - added a possibility to receive remote ip address during read event

0.2.2 (Mar 12, 2016)
--------------------
### Changes:
   - fix: properly work on php versions when _socket_ extension is not available
   - bug [#1](https://github.com/edefimov/async-sockets/issues/1): fixes bug leading to CPU overloading
   
0.2.1 (Dec 20, 2015)
--------------------
### Changes:
   - fixes incorrect select operation processing on server sockets

0.2.0 (Jul 25, 2015)
--------------------
### Changes:
  - Removed support of synchronous I/O
  - Server socket support
  - Support all transports returned by `stream_get_transports`
  - Distinguish frame boundaries
  - Determine datagram size for udp sockets
  - Improved working with TLS sockets
  
  
0.2.0-alpha (Jul 1, 2015)
--------------------
### New features:
  - Server socket support
  - Support all transports returned by `stream_get_transports`
  - Distinguish frame boundaries
  - Determine datagram size for udp sockets
 
0.1.1 (May 25, 2015)
--------------------
### Changes:
 - Added additional checks due to https://bugs.php.net/bug.php?id=64803

0.1.0 (May 19, 2015)
--------------------
 - First library release
