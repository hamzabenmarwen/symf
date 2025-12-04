<?php

namespace App\Command;

use App\Repository\EmpruntRepository;
use App\Service\NotificationService;
use App\Service\OverdueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-notifications',
    description: 'Send borrowing reminders and overdue notices (run daily via cron)',
)]
class SendNotificationsCommand extends Command
{
    public function __construct(
        private EmpruntRepository $empruntRepository,
        private NotificationService $notificationService,
        private OverdueService $overdueService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('📧 Sending Borrowing Notifications');

        $sent = 0;
        $failed = 0;

        // 1. Send reminders for books due in 3 days
        $io->section('📬 Sending return reminders (due in 3 days)...');
        $activeBorrowings = $this->empruntRepository->findBy([
            'dateRetourEffective' => null
        ]);

        foreach ($activeBorrowings as $borrowing) {
            $daysRemaining = $this->getDaysRemaining($borrowing->getDateRetourPrevue());
            
            // Send reminder if due in exactly 3 days
            if ($daysRemaining === 3) {
                if ($this->notificationService->sendReturnReminder($borrowing)) {
                    $io->writeln("✓ Reminder sent to {$borrowing->getUtilisateur()->getEmail()}");
                    $sent++;
                } else {
                    $io->writeln("✗ Failed to send reminder to {$borrowing->getUtilisateur()->getEmail()}");
                    $failed++;
                }
            }
        }

        // 2. Send overdue notices for books past return date
        $io->section('⚠️ Sending overdue notices...');
        $overdueBooks = $this->overdueService->getAllOverdueBooks();

        foreach ($overdueBooks as $borrowing) {
            if ($this->notificationService->sendOverdueNotice($borrowing)) {
                $io->writeln("✓ Overdue notice sent to {$borrowing->getUtilisateur()->getEmail()}");
                $sent++;
            } else {
                $io->writeln("✗ Failed to send overdue notice to {$borrowing->getUtilisateur()->getEmail()}");
                $failed++;
            }
        }

        // 3. Update statuses
        $io->section('🔄 Updating borrowing statuses...');
        $updated = $this->overdueService->updateAllBorrowingStatuses();
        $io->writeln("Updated <info>$updated</info> borrowings status");

        // Summary
        $io->newLine();
        $io->success("✅ Notifications sent: <info>$sent</info> | Failed: <info>$failed</info>");

        return Command::SUCCESS;
    }

    private function getDaysRemaining(\DateTimeImmutable $dueDate): int
    {
        $now = new \DateTimeImmutable('today');
        return (int)$now->diff($dueDate)->format('%r%a');
    }
}
