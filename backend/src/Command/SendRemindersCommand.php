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
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Rappel par e-mail des séances qui commencent dans les prochaines 24 heures (US-S08).
 *
 * Une réservation ne reçoit qu'un seul rappel (`reminderSentAt`). Chaque rappel est d'abord
 * « réservé » par une mise à jour conditionnelle (… WHERE reminder_sent_at IS NULL) : si deux
 * passages se chevauchent, un seul envoie l'e-mail. En cas d'échec d'envoi, la marque est
 * retirée et le rappel sera retenté. À planifier toutes les heures : tâche planifiée Windows
 * ou cron, par exemple
 *   0 * * * *  php bin/console app:send-reminders
 */
#[AsCommand(name: 'app:send-reminders', description: 'Envoie le rappel 24 h avant chaque séance réservée.')]
final class SendRemindersCommand extends Command
{
    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly FitBookMailer $mailer,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
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
            // Déjà pris en charge par un autre passage de la commande : rien à faire.
            if (!$this->reservations->claimReminder($reservation, $now)) {
                continue;
            }
            $reservation->setReminderSentAt($now);

            try {
                $this->mailer->sendReminder($reservation);
            } catch (\Throwable $exception) {
                // Marque retirée : le rappel sera retenté au prochain passage.
                $this->reservations->releaseReminder($reservation);
                $reservation->setReminderSentAt(null);
                ++$failed;
                $io->warning(sprintf('Réservation %d : échec de l’envoi (%s).', $reservation->getId(), $exception::class));
                continue;
            }

            // Notification dans la langue du sportif, comme l'e-mail de rappel.
            $message = $this->translator->trans(
                'Rappel : « {title} » commence bientôt.',
                ['{title}' => (string) $reservation->getSession()?->getTitle()],
                'messages',
                $reservation->getUser()?->getLocale() ?? 'fr',
            );
            $this->em->persist((new Notification())
                ->setUser($reservation->getUser())
                ->setType('rappel')
                ->setMessage(mb_substr($message, 0, 255)));
            // Enregistré après chaque envoi : une erreur plus loin ne fait rien renvoyer.
            $this->em->flush();
            ++$sent;
        }

        $io->success(sprintf('%d rappel(s) envoyé(s), %d échec(s).', $sent, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
