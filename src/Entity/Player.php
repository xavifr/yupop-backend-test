<?php

namespace App\Entity;

use App\Repository\PlayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\UniqueConstraint(
    name: 'game_position_idx',
    columns: ['game_id', 'position']
)]
#[ORM\HasLifecycleCallbacks]
class Player
{
    const STATE_WAITING = 'waiting';
    const STATE_PLAYING = 'playing';
    const STATE_FINISHED = 'finished';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255, options: ['default' => self::STATE_WAITING])]
    private ?string $state = self::STATE_WAITING;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $final_score = 0;

    #[ORM\Column]
    private ?int $position = null;

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Game $game = null;

    #[ORM\OneToMany(mappedBy: 'player', targetEntity: Frame::class, orphanRemoval: true)]
    #[ORM\OrderBy(value: ["round"=>"ASC"])]
    private Collection $frames;

    #[ORM\Column(options: ['default' => 0])]
    private ?int $last_round = 0;

    public function __construct()
    {
        $this->frames = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function getFinalScore(): ?int
    {
        return $this->final_score;
    }

    public function setFinalScore(int $final_score): self
    {
        $this->final_score = $final_score;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;

        return $this;
    }

    /**
     * @return Collection<int, Frame>
     */
    public function getFrames(): Collection
    {
        return $this->frames;
    }

    public function addFrame(Frame $frame): self
    {
        if (!$this->frames->contains($frame)) {
            $this->frames->add($frame);
            $frame->setPlayer($this);
        }

        return $this;
    }

    public function removeFrame(Frame $frame): self
    {
        if ($this->frames->removeElement($frame)) {
            // set the owning side to null (unless already changed)
            if ($frame->getPlayer() === $this) {
                $frame->setPlayer(null);
            }
        }

        return $this;
    }

    #[ORM\PrePersist]
    public function setPositionValue()
    {
        $this->position = $this->getGame()->getPlayers()->count();
    }

    public function getLastRound(): ?int
    {
        return $this->last_round;
    }

    public function setLastRound(int $last_round): self
    {
        $this->last_round = $last_round;

        return $this;
    }
}
