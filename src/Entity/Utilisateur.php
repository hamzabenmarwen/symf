<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    #[Assert\Length(max: 180, maxMessage: 'Email must be less than {{ limit }} characters')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Emprunt>
     */
    #[ORM\OneToMany(targetEntity: Emprunt::class, mappedBy: 'utilisateur')]
    private Collection $emprunts;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'First name must be less than {{ limit }} characters')]
    #[Assert\Regex(pattern: '/^[a-zA-Z\s\-\']*$/', message: 'First name contains invalid characters')]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Last name must be less than {{ limit }} characters')]
    #[Assert\Regex(pattern: '/^[a-zA-Z\s\-\']*$/', message: 'Last name contains invalid characters')]
    private ?string $lastName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profileImage = null;

    #[ORM\Column(nullable: true)]
    private ?int $totalBorrowings = 0;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Wishlist>
     */
    #[ORM\OneToMany(targetEntity: Wishlist::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $wishlists;

    /**
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $reservations;

    /**
     * @var Collection<int, Notification>
     */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $notifications;

    /**
     * @var Collection<int, Avis>
     */
    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'utilisateur', cascade: ['remove'])]
    private Collection $avis;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiredAt = null;

    public function __construct()
    {
        $this->emprunts = new ArrayCollection();
        $this->wishlists = new ArrayCollection();
        $this->reservations = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->avis = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    /**
     * @return Collection<int, Emprunt>
     */
    public function getEmprunts(): Collection
    {
        return $this->emprunts;
    }

    public function addEmprunt(Emprunt $emprunt): static
    {
        if (!$this->emprunts->contains($emprunt)) {
            $this->emprunts->add($emprunt);
            $emprunt->setUtilisateur($this);
        }

        return $this;
    }

    public function removeEmprunt(Emprunt $emprunt): static
    {
        if ($this->emprunts->removeElement($emprunt)) {
            // set the owning side to null (unless already changed)
            if ($emprunt->getUtilisateur() === $this) {
                $emprunt->setUtilisateur(null);
            }
        }

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getProfileImage(): ?string
    {
        return $this->profileImage;
    }

    public function setProfileImage(?string $profileImage): static
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function getTotalBorrowings(): int
    {
        return count($this->emprunts);
    }

    public function setTotalBorrowings(?int $totalBorrowings): static
    {
        $this->totalBorrowings = $totalBorrowings;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @return Collection<int, Wishlist>
     */
    public function getWishlists(): Collection
    {
        return $this->wishlists;
    }

    public function addWishlist(Wishlist $wishlist): static
    {
        if (!$this->wishlists->contains($wishlist)) {
            $this->wishlists->add($wishlist);
            $wishlist->setUtilisateur($this);
        }
        return $this;
    }

    public function removeWishlist(Wishlist $wishlist): static
    {
        if ($this->wishlists->removeElement($wishlist)) {
            if ($wishlist->getUtilisateur() === $this) {
                $wishlist->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function addReservation(Reservation $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setUtilisateur($this);
        }
        return $this;
    }

    public function removeReservation(Reservation $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getUtilisateur() === $this) {
                $reservation->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): static
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUtilisateur($this);
        }
        return $this;
    }

    public function removeNotification(Notification $notification): static
    {
        if ($this->notifications->removeElement($notification)) {
            if ($notification->getUtilisateur() === $this) {
                $notification->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Avis>
     */
    public function getAvis(): Collection
    {
        return $this->avis;
    }

    public function addAvis(Avis $avis): static
    {
        if (!$this->avis->contains($avis)) {
            $this->avis->add($avis);
            $avis->setUtilisateur($this);
        }
        return $this;
    }

    public function removeAvis(Avis $avis): static
    {
        if ($this->avis->removeElement($avis)) {
            if ($avis->getUtilisateur() === $this) {
                $avis->setUtilisateur(null);
            }
        }
        return $this;
    }

    // ────────────────────────────────────────
    // STATISTICS METHODS
    // ────────────────────────────────────────
    // ────────────────────────────────────────
    // STATISTICS METHODS
    // ────────────────────────────────────────

    /**
     * Get number of active borrowings (not returned)
     */
    public function getActiveBorrowings(): int
    {
        return $this->emprunts->filter(function (Emprunt $e) {
            return $e->getDateRetourEffective() === null;
        })->count();
    }

    /**
     * Get number of completed borrowings (returned)
     */
    public function getCompletedBorrowings(): int
    {
        return $this->emprunts->filter(function (Emprunt $e) {
            return $e->getDateRetourEffective() !== null;
        })->count();
    }

    /**
     * Get number of overdue borrowings
     */
    public function getOverdueBorrowings(): int
    {
        $now = new \DateTimeImmutable();
        return $this->emprunts->filter(function (Emprunt $e) use ($now) {
            return $e->getDateRetourEffective() === null && 
                   $e->getDateRetourPrevue() < $now;
        })->count();
    }

    /**
     * Get average rating of user's reviews
     */
    public function getAverageReviewRating(): float
    {
        if ($this->avis->isEmpty()) {
            return 0;
        }

        $sum = 0;
        foreach ($this->avis as $avis) {
            $sum += $avis->getRating();
        }

        return round($sum / count($this->avis), 1);
    }

    /**
     * Get total number of reviews by this user
     */
    public function getTotalReviews(): int
    {
        return count($this->avis);
    }

    /**
     * Get total number of wishlist items
     */
    public function getTotalWishlistItems(): int
    {
        return count($this->wishlists);
    }

    /**
     * Get member duration in days
     */
    public function getMemberSinceDays(): int
    {
        if ($this->createdAt === null) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        return (int)$now->diff($this->createdAt)->format('%a');
    }

    /**
     * Get member status (basic, active, power user)
     */
    public function getMemberStatus(): string
    {
        $score = $this->getTotalBorrowings() + $this->getTotalReviews();

        if ($score >= 50) {
            return 'power-user';
        } elseif ($score >= 20) {
            return 'active';
        }

        return 'basic';
    }

    /**
     * Get formatted member since date
     */
    public function getFormattedCreatedAt(): string
    {
        if ($this->createdAt === null) {
            return 'Unknown';
        }

        return $this->createdAt->format('d/m/Y');
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;

        return $this;
    }

    public function getResetTokenExpiredAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiredAt;
    }

    public function setResetTokenExpiredAt(?\DateTimeImmutable $resetTokenExpiredAt): static
    {
        $this->resetTokenExpiredAt = $resetTokenExpiredAt;

        return $this;
    }

    public function isResetTokenValid(): bool
    {
        return $this->resetToken !== null && $this->resetTokenExpiredAt !== null && $this->resetTokenExpiredAt > new \DateTimeImmutable();
    }
}
