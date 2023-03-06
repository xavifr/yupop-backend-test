<?php

namespace App\Message;

class FrameRollPropagationMessage
{
    public function __construct(
        private int $id,
        private int $pins_rolled = 0,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPinsRolled(): int
    {
        return $this->pins_rolled;
    }
}
