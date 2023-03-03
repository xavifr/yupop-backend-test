<?php

namespace App\Message;

final class GameMessage
{
    public function __construct(
        private int $id,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

}
