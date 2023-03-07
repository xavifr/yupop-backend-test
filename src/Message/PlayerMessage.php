<?php

namespace App\Message;

class PlayerMessage
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
