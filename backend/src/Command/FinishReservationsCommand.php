<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ReservationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Passe à « terminée » les réservations confirmées des séances finies (ouvre le droit
 * de laisser un avis, RG-06). L'API le fait aussi avant chaque dépôt d'avis ; la commande
 * garde l'historique à jour. À planifier toutes les heures avec app:send-reminders.
 */
#[AsCommand(name: 'app:reservations:terminer', description: 'Marque comme terminées les réservations des séances passées.')]
final class FinishReservationsCommand extends Command
{
    public function __construct(private readonly ReservationRepository $reservations)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->reservations->markFinished(new \DateTimeImmutable());
        (new SymfonyStyle($input, $output))->success(sprintf('%d réservation(s) terminée(s).', $count));

        return Command::SUCCESS;
    }
}
