UPGRADE from 0.2 to 0.3
-----------------------

 - The `event` parameter was removed from `AsyncSockets\Event\SocketExceptionEvent`, all calls to `$exceptionEvent->getOriginalEvent()` method should be removed.
 - Implementations of the `OperationInterface` have changed namespace from `AsyncSockets\RequestExecutor` to `AsyncSockets\Operation`. All usages of `ReadOperation` and `WriteOperation` must be replaced:

     instead of: `AsyncSockets\RequestExecutor\ReadOperation`
     should be: `AsyncSockets\Operation\ReadOperation`

     instead of: `AsyncSockets\RequestExecutor\WriteOperation`
     should be: `AsyncSockets\Operation\WriteOperation`

 - The `$remoteAddress` parameter was added into method `pickUpData()` from `AsyncSockets\Frame\FramePickerInterface`. If you have implemented this method you need to change its signature to:
 
```php
   public function pickUpData($chunk, $remoteAddress);
```

 - The `getRemoteAddress()` method was added into `AsyncSockets\Frame\FrameInterface`. If you have implemented this interface add the corresponding implementation. Use the return value of `pickUpData()` method from `FramePickerInterface`

```php
   public function getRemoteAddress();
```

 - The `getClientAddress()` method was removed from `AsyncSockets\Frame\AcceptedFrame`. Use `getRemoteAddress()` method instead.

 - The `$picker` parameter is now required in the read() method from `AsyncSockets\Socket\SocketInterface`

 - `NullFramePicker` was removed. If you used it somewhere explicitly you should replace it with other corresponding implemntation of `FramePickerInterface` 

 - The `FrameSocketException` is renamed into `FrameException`.
