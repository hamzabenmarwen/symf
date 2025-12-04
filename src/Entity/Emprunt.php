<?php

namespace App\Entity;

use App\Repository\EmpruntRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmpruntRepository::class)]
class Emprunt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateEmprunt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateRetourPrevue = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateRetourEffective = null;

    #[ORM\ManyToOne(inversedBy: 'emprunts')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'emprunts')]
    private ?Livre $livre = null;

    #[ORM\Column(length: 50, options: ['default' => 'active'])]
    private string $status = 'active'; // active, overdue, returned

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->dateEmprunt = $now;
        $this->dateRetourPrevue = $now->modify('+14 days');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateEmprunt(): ?\DateTimeImmutable
    {
        return $this->dateEmprunt;
    }

    public function setDateEmprunt(?\DateTimeImmutable $dateEmprunt): static
    {
        $this->dateEmprunt = $dateEmprunt;
        return $this;
    }

    public function getDateRetourPrevue(): ?\DateTimeImmutable
    {
        return $this->dateRetourPrevue;
    }

    public function setDateRetourPrevue(?\DateTimeImmutable $dateRetourPrevue): static
    {
        $this->dateRetourPrevue = $dateRetourPrevue;
        return $this;
    }

    public function getDateRetourEffective(): ?\DateTimeImmutable
    {
        return $this->dateRetourEffective;
    }

    public function setDateRetourEffective(?\DateTimeImmutable $dateRetourEffective): static
    {
        $this->dateRetourEffective = $dateRetourEffective;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getLivre(): ?Livre
    {
        return $this->livre;
    }

    public function setLivre(?Livre $livre): static
    {
        $this->livre = $livre;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isOverdue(): bool
    {
        if ($this->status === 'returned' || $this->dateRetourEffective !== null) {
            return false;
        }
        return $this->dateRetourPrevue < new \DateTimeImmutable();
    }
}