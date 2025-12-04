<?php

namespace App\Service;

use App\Entity\Emprunt;
use App\Entity\Utilisateur;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
        private string $senderEmail = 'noreply@bibliotheque.com'
    ) {}

    /**
     * Send return reminder email (3 days before due date)
     */
    public function sendReturnReminder(Emprunt $emprunt): bool
    {
        try {
            $user = $emprunt->getUtilisateur();
            $livre = $emprunt->getLivre();
            $daysRemaining = $this->calculateDaysRemaining($emprunt->getDateRetourPrevue());

            $html = $this->twig->render('emails/return_reminder.html.twig', [
                'user' => $user,
                'livre' => $livre,
                'emprunt' => $emprunt,
                'daysRemaining' => $daysRemaining,
            ]);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject("Rappel : Retour du livre \"{$livre->getTitre()}\" dans {$daysRemaining} jour(s)")
                ->html($html);

            $this->mailer->send($email);
            $this->logger->info('Return reminder email sent', [
                'user' => $user->getEmail(),
                'book' => $livre->getTitre(),
                'days_remaining' => $daysRemaining
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send return reminder', [
                'error' => $e->getMessage(),
                'user' => $emprunt->getUtilisateur()->getEmail()
            ]);
            return false;
        }
    }

    /**
     * Send overdue notice email
     */
    public function sendOverdueNotice(Emprunt $emprunt): bool
    {
        try {
            $user = $emprunt->getUtilisateur();
            $livre = $emprunt->getLivre();
            $daysOverdue = $this->calculateDaysOverdue($emprunt->getDateRetourPrevue());

            $html = $this->twig->render('emails/overdue_notice.html.twig', [
                'user' => $user,
                'livre' => $livre,
                'emprunt' => $emprunt,
                'daysOverdue' => $daysOverdue,
            ]);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject("⚠️ Livre en retard : \"{$livre->getTitre()}\" ({$daysOverdue} jour(s))")
                ->html($html);

            $this->mailer->send($email);
            $this->logger->warning('Overdue notice sent', [
                'user' => $user->getEmail(),
                'book' => $livre->getTitre(),
                'days_overdue' => $daysOverdue
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send overdue notice', [
                'error' => $e->getMessage(),
                'user' => $emprunt->getUtilisateur()->getEmail()
            ]);
            return false;
        }
    }

    /**
     * Send book available notification (after reservation/wishlist)
     */
    public function sendBookAvailableNotification(Utilisateur $user, string $bookTitle): bool
    {
        try {
            $html = $this->twig->render('emails/book_available.html.twig', [
                'user' => $user,
                'bookTitle' => $bookTitle,
            ]);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject("📚 Le livre \"{$bookTitle}\" est maintenant disponible !")
                ->html($html);

            $this->mailer->send($email);
            $this->logger->info('Book available notification sent', [
                'user' => $user->getEmail(),
                'book' => $bookTitle
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send book available notification', [
                'error' => $e->getMessage(),
                'user' => $user->getEmail()
            ]);
            return false;
        }
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(Utilisateur $user): bool
    {
        try {
            $html = $this->twig->render('emails/welcome.html.twig', [
                'user' => $user,
            ]);

            $email = (new Email())
                ->from($this->senderEmail)
                ->to($user->getEmail())
                ->subject('Bienvenue à la Bibliothèque ! 📚')
                ->html($html);

            $this->mailer->send($email);
            $this->logger->info('Welcome email sent', [
                'user' => $user->getEmail()
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'error' => $e->getMessage(),
                'user' => $user->getEmail()
            ]);
            return false;
        }
    }

    /**
     * Helper: Calculate days remaining
     */
    private function calculateDaysRemaining(\DateTimeImmutable $dueDate): int
    {
        $now = new \DateTimeImmutable('today');
        return (int)$now->diff($dueDate)->format('%r%a');
    }

    /**
     * Helper: Calculate days overdue
     */
    private function calculateDaysOverdue(\DateTimeImmutable $dueDate): int
    {
        $now = new \DateTimeImmutable('today');
        $diff = $dueDate->diff($now);
        return (int)$diff->format('%a');
    }
}
