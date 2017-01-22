<?php
/**
 * Async sockets
 *
 * @copyright Copyright (c) 2015-2017, Efimov Evgenij <edefimov.it@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AsyncSockets\RequestExecutor\Pipeline;

/**
 * Class PushbackIterator
 */
class PushbackIterator implements \Iterator
{
    /**
     * Nested iterator
     *
     * @var \Iterator
     */
    private $nestedIterator;

    /**
     * Unread item from previous iteration
     *
     * @var string[]
     */
    private $unreadItems;

    /**
     * Result of previous iteration
     *
     * @var string
     */
    private $lastIterationResult;

    /**
     * String length to return from this iterator
     *
     * @var int
     */
    private $chunkSize;

    /**
     * Iteration key
     *
     * @var int
     */
    private $key;

    /**
     * Length of last result push back
     *
     * @var int
     */
    private $backLength;

    /**
     * PushbackIterator constructor.
     *
     * @param \Iterator $nestedIterator Nested iterator
     * @param int       $chunkSize      Max length of string to return
     */
    public function __construct(\Iterator $nestedIterator, $chunkSize)
    {
        $this->nestedIterator = $nestedIterator;
        $this->chunkSize      = $chunkSize;
        $this->unreadItems    = [];
    }

    /**
     * @inheritDoc
     */
    public function current()
    {
        return $this->lastIterationResult;
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        ++$this->key;

        $hasUnreadItem = $this->hasUnreadItem();
        if (!$hasUnreadItem && !$this->backLength) {
            $this->nestedIterator->next();
            if (!$this->nestedIterator->valid()) {
                $this->lastIterationResult = '';
                return;
            }
        }

        $this->lastIterationResult = $this->buildChunk();
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return $this->key;
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        return !empty($this->lastIterationResult) || $this->hasUnreadItem() || $this->nestedIterator->valid();
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->key                 = 0;
        $this->backLength          = 0;
        $this->unreadItems         = [];
        $this->nestedIterator->rewind();
        $this->lastIterationResult = $this->buildChunk();
    }

    /**
     * Return given length of previously read string back into iterator
     *
     * @param int $length Length of string to return back
     *
     * @return void
     */
    public function unread($length)
    {
        if (empty($this->lastIterationResult) || $length <= 0) {
            return;
        }

        $itemLength       = strlen($this->lastIterationResult);
        $this->backLength = min($itemLength, $this->backLength + $length);
    }

    /**
     * Test if iterator has unread item
     *
     * @return bool
     */
    private function hasUnreadItem()
    {
        return !empty($this->unreadItems);
    }

    /**
     * Build chunk to return to user
     *
     * @return string
     */
    private function buildChunk()
    {
        $result = '';
        $this->popLastPushedBackItem();

        $result .= $this->hasUnreadItem() ?
            array_shift($this->unreadItems) :
            (string) $this->nestedIterator->current();

        while ($this->hasUnreadItem() && strlen($result) < $this->chunkSize) {
            $result .= array_shift($this->unreadItems);
        }

        while (strlen($result) < $this->chunkSize && $this->nestedIterator->valid()) {
            $this->nestedIterator->next();
            $result .= (string) $this->nestedIterator->current();
        }

        $split  = str_split($result, $this->chunkSize);
        $result = array_shift($split);
        if (!empty($split)) {
            $this->unreadItems = array_merge($split, $this->unreadItems);
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function __toString()
    {
        return $this->valid() ? $this->current() : '';
    }

    /**
     * Return an item caused by unread operation
     *
     * @return void
     */
    private function popLastPushedBackItem()
    {
        if ($this->backLength) {
            $item = substr(
                $this->lastIterationResult,
                strlen($this->lastIterationResult) - $this->backLength,
                $this->backLength
            );

            array_unshift($this->unreadItems, $item);

            $this->backLength = 0;
        }
    }
}
