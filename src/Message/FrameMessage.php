<?php

namespace App\Message;

final class FrameMessage
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

    public function getPinsRolled(): array
    {
        return $this->pins_rolled;
    }

}
