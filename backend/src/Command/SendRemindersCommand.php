<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Repository\ReservationRepository;
use App\Service\FitBookMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rappel par e-mail des séances qui commencent dans les prochaines 24 heures (US-S08).
 *
 * Une réservation ne reçoit qu'un seul rappel (`reminderSentAt`). À planifier toutes les
 * heures : tâche planifiée Windows ou cron, par exemple
 *   0 * * * *  php bin/console app:send-reminders
 */
#[AsCommand(name: 'app:send-reminders', description: 'Envoie le rappel 24 h avant chaque séance réservée.')]
final class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly FitBookMailer $mailer,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('heures', null, InputOption::VALUE_REQUIRED, 'Fenêtre en heures avant la séance', '24');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = max(1, (int) $input->getOption('heures'));
        $now = new \DateTimeImmutable();

        $sent = 0;
        $failed = 0;
        foreach ($this->reservations->dueForReminder($now, $now->modify(sprintf('+%d hours', $hours))) as $reservation) {
            try {
                $this->mailer->sendReminder($reservation);
            } catch (\Throwable $exception) {
                // Le rappel sera retenté au prochain passage.
                ++$failed;
                $io->warning(sprintf('Réservation %d : échec de l’envoi (%s).', $reservation->getId(), $exception::class));
                continue;
            }

            $reservation->setReminderSentAt($now);
            $this->em->persist((new Notification())
                ->setUser($reservation->getUser())
                ->setType('rappel')
                ->setMessage(mb_substr(sprintf('Rappel : « %s » commence bientôt.', $reservation->getSession()?->getTitle()), 0, 255)));
            ++$sent;
        }
        $this->em->flush();

        $io->success(sprintf('%d rappel(s) envoyé(s), %d échec(s).', $sent, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
