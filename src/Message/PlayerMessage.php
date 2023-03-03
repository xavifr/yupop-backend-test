<?php

namespace App\Message;

final class PlayerMessage
{

    public function __construct(
        private int $id,
        private int $next_round = 0,
    )
    {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNextRound(): int
    {
        return $this->next_round;
    }

}
