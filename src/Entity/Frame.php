<?php

namespace App\Entity;

use App\Repository\FrameRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FrameRepository::class)]
#[ORM\UniqueConstraint(
    name: 'player_round_idx',
    columns: ['player_id', 'round']
)]
class Frame
{
    const STATE_NEW = 'new';
    const STATE_SECOND_ROLL = 'second_roll';
    const STATE_THIRD_ROLL = 'third_roll';
    const STATE_WAIT_SCORE = 'wait_score';
    const STATE_DONE = 'done';

    const PINS_PER_FRAME = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $round = null;

    #[ORM\Column(length: 255, options: ['default' => self::STATE_NEW])]
    private ?string $state = self::STATE_NEW;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $score_wait = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $roll_1 = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $roll_2 = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $roll_3 = 0;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $score = 0;

    #[ORM\ManyToOne(inversedBy: 'frames')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Player $player = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRound(): ?int
    {
        return $this->round;
    }

    public function setRound(int $round): self
    {
        $this->round = $round;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getScoreWait(): ?int
    {
        return $this->score_wait;
    }

    public function setScoreWait(int $score_wait): self
    {
        $this->score_wait = $score_wait;

        return $this;
    }

    public function getRoll1(): ?int
    {
        return $this->roll_1;
    }

    public function setRoll1(int $roll_1): self
    {
        $this->roll_1 = $roll_1;
        $this->score += $roll_1;

        return $this;
    }

    public function getRoll2(): ?int
    {
        return $this->roll_2;
    }

    public function setRoll2(int $roll_2): self
    {
        $this->roll_2 = $roll_2;
        $this->score += $roll_2;

        return $this;
    }


    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getPlayer(): ?Player
    {
        return $this->player;
    }

    public function setPlayer(?Player $player): self
    {
        $this->player = $player;

        return $this;
    }

    public function getRoll3(): ?int
    {
        return $this->roll_3;
    }

    public function setRoll3(int $roll_3): self
    {
        $this->roll_3 = $roll_3;
        $this->score += $roll_3;

        return $this;
    }
}
