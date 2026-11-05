<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Activite;
use App\Entity\Coach;
use App\Entity\Salle;
use App\Entity\Seance;
use App\Repository\ActiviteRepository;
use App\Repository\CoachRepository;
use App\Repository\SalleRepository;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Idempotente : les éléments existants (même nom / e-mail / titre + date) sont réactivés au lieu d'être dupliqués.
#[AsCommand(name: 'app:demo:seed', description: 'Remplit le catalogue FitBook avec des activités, coaches, salles et séances à venir.')]
final class SeedDemoCommand extends Command
{
    private const ACTIVITES = [
        ['Yoga', 'Cours de yoga pour débutants', 60, 'Débutant'],
        ['Pilates', 'Renforcement profond et posture', 45, 'Intermédiaire'],
        ['Boxe', 'Cardio-boxe et technique de frappe', 60, 'Intermédiaire'],
        ['Running', 'Course en groupe avec travail de fractionné', 50, 'Tous niveaux'],
        ['CrossTraining', 'Circuit haute intensité', 45, 'Avancé'],
    ];

    private const COACHES = [
        ['Niyonzima', 'Jean', 'jean@example.com', 'Yoga', 'Coach de yoga pour débutants', '8 ans', 'RYT 200', '25.00'],
        ['Irakoze', 'Aline', 'aline.irakoze@example.com', 'Pilates', 'Spécialiste du renforcement et de la mobilité', '5 ans', 'Pilates Mat', '30.00'],
        ['Hakizimana', 'Eric', 'eric.hakizimana@example.com', 'Boxe', 'Ancien boxeur, coach cardio-boxe', '10 ans', 'BPJEPS Boxe', '35.00'],
        ['Uwase', 'Claire', 'claire.uwase@example.com', 'CrossTraining', 'Préparation physique et circuits HIIT', '6 ans', 'CrossFit L1', '32.00'],
    ];

    private const SALLES = [
        ['Salle A', 'Bujumbura', 20, 'Salle pour les cours de yoga', 'Tapis, briques, sangles'],
        ['Studio Lumière', 'Rohero, Bujumbura', 15, 'Studio calme et lumineux', 'Reformers, tapis, miroirs'],
        ['Ring Central', 'Kinindo, Bujumbura', 25, 'Espace de combat et cardio', 'Ring, sacs de frappe, cordes'],
    ];

    /** Activité, coach (e-mail), salle, jour (+N), heure, prix */
    private const PLANNING = [
        ['Yoga', 'jean@example.com', 'Salle A', 1, '09:00', '20.00'],
        ['Pilates', 'aline.irakoze@example.com', 'Studio Lumière', 1, '18:00', '22.00'],
        ['Boxe', 'eric.hakizimana@example.com', 'Ring Central', 2, '19:00', '25.00'],
        ['CrossTraining', 'claire.uwase@example.com', 'Ring Central', 3, '07:00', '18.00'],
        ['Yoga', 'jean@example.com', 'Studio Lumière', 4, '12:30', '20.00'],
        ['Running', 'claire.uwase@example.com', 'Salle A', 5, '08:00', '12.00'],
        ['Boxe', 'eric.hakizimana@example.com', 'Ring Central', 7, '18:30', '25.00'],
        ['Pilates', 'aline.irakoze@example.com', 'Studio Lumière', 8, '10:00', '22.00'],
        ['Yoga', 'jean@example.com', 'Salle A', 10, '17:00', '20.00'],
        ['CrossTraining', 'claire.uwase@example.com', 'Ring Central', 12, '07:00', '18.00'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActiviteRepository $activites,
        private readonly CoachRepository $coaches,
        private readonly SalleRepository $salles,
        private readonly SeanceRepository $seances,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = ['activités' => 0, 'coaches' => 0, 'salles' => 0, 'séances' => 0];

        $activites = [];
        foreach (self::ACTIVITES as [$nom, $description, $duree, $niveau]) {
            $item = $this->activites->findOneBy(['nom' => $nom]);
            if (!$item) {
                $item = (new Activite())->setNom($nom)->setDescription($description)->setDuree($duree)->setNiveau($niveau);
                $this->em->persist($item);
                ++$created['activités'];
            }
            $activites[$nom] = $item->setStatut('actif');
        }

        $coaches = [];
        foreach (self::COACHES as [$nom, $prenom, $email, $specialite, $bio, $experience, $certification, $tarif]) {
            $item = $this->coaches->findOneBy(['email' => $email]);
            if (!$item) {
                $item = (new Coach())->setNom($nom)->setPrenom($prenom)->setEmail($email)->setTelephone('+257 79 000 000')
                    ->setSpecialite($specialite)->setDescription($bio);
                $this->em->persist($item);
                ++$created['coaches'];
            }
            // Les coaches ne se connectent pas : on remplace tout mot de passe en clair par un hash inutilisable.
            if (!str_starts_with((string) $item->getMotDePasse(), '$')) {
                $item->setMotDePasse(password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT));
            }
            $item->setExperience($item->getExperience() ?? $experience)
                ->setCertification($item->getCertification() ?? $certification)
                ->setTarifHoraire($item->getTarifHoraire() ?? $tarif)
                ->setStatut('actif');
            $coaches[$email] = $item;
        }

        $salles = [];
        foreach (self::SALLES as [$nom, $adresse, $capacite, $description, $equipements]) {
            $item = $this->salles->findOneBy(['nom' => $nom]);
            if (!$item) {
                $item = (new Salle())->setNom($nom)->setAdresse($adresse)->setCapacite($capacite)->setDescription($description);
                $this->em->persist($item);
                ++$created['salles'];
            }
            $item->setEquipements($item->getEquipements() ?? $equipements)->setStatut('actif');
            $salles[$nom] = $item;
        }

        $today = new \DateTimeImmutable('today');
        foreach (self::PLANNING as [$activiteNom, $coachEmail, $salleNom, $jour, $heure, $prix]) {
            $activite = $activites[$activiteNom];
            $salle = $salles[$salleNom];
            $debut = $today->modify("+{$jour} days {$heure}");
            $fin = $debut->modify('+' . $activite->getDuree() . ' minutes');
            $titre = $activiteNom . ' – ' . $activite->getNiveau();
            if ($this->seances->findOneBy(['titre' => $titre, 'dateDebut' => $debut])) {
                continue;
            }
            $capacite = min(15, (int) $salle->getCapacite());
            $this->em->persist((new Seance())
                ->setTitre($titre)
                ->setDescription($activite->getDescription())
                ->setDateDebut($debut)->setDateFin($fin)
                ->setDateSeance($debut->setTime(0, 0))
                ->setHeureDebut(new \DateTimeImmutable('1970-01-01 ' . $debut->format('H:i:s')))
                ->setHeureFin(new \DateTimeImmutable('1970-01-01 ' . $fin->format('H:i:s')))
                ->setCapaciteMax($capacite)->setPlacesDisponibles($capacite)
                ->setPrix($prix)->setStatut('actif')
                ->setActivite($activite)->setCoach($coaches[$coachEmail])->setSalle($salle));
            ++$created['séances'];
        }

        $this->em->flush();

        $io->success(sprintf(
            'Catalogue prêt : %s créé(s). Les éléments existants ont été réactivés.',
            implode(', ', array_map(fn (string $k, int $v): string => "{$v} {$k}", array_keys($created), $created)),
        ));

        return Command::SUCCESS;
    }
}
