<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Activity;
use App\Entity\Availability;
use App\Entity\CoachProfile;
use App\Entity\Favorite;
use App\Entity\Gym;
use App\Entity\Notification;
use App\Entity\Reservation;
use App\Entity\Review;
use App\Entity\Session;
use App\Entity\User;
use App\Enum\ReservationStatus;
use App\Enum\SessionLevel;
use App\Enum\SessionStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de données de démonstration (Bruxelles).
 *
 * Contenu : 1 admin, 5 gestionnaires, 10 salles, 15 coachs, 10 activités, 80 séances à venir
 * (sur 4 semaines) + 20 séances passées, 40 sportifs, réservations, favoris, avis, disponibilités.
 *
 * - Déterministe : la graine Faker est fixe, chaque chargement donne les mêmes données
 *   (seules les dates suivent le jour du chargement).
 * - Comptes de démo documentés dans le README ; mots de passe de démonstration uniquement.
 * - Les séances sont créées à l'heure de Bruxelles puis stockées en UTC.
 * - Les photos réutilisent celles du site actuel (charte graphique conservée).
 *
 * Chargement :  symfony console doctrine:fixtures:load
 */
final class AppFixtures extends Fixture
{
    /** Mots de passe de démonstration, un par rôle (jamais utilisés en production). */
    public const PASSWORDS = [
        User::ROLE_ADMIN => 'Admin123!',
        User::ROLE_GESTIONNAIRE => 'Gestion123!',
        User::ROLE_COACH => 'Coach123!',
        User::ROLE_SPORTIF => 'Sportif123!',
    ];

    private const SEED = 20261105;
    private const LOCAL_TIMEZONE = 'Europe/Brussels';
    private const UPCOMING_SESSIONS = 80;
    private const PAST_SESSIONS = 20;

    /** Photos du site actuel (Unsplash), par thème. */
    private const PHOTO = [
        'yoga' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72',
        'boxe' => 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=900&q=72',
        'course' => 'https://images.unsplash.com/photo-1552674605-46e432e1a7ee?auto=format&fit=crop&w=900&q=72',
        'muscu' => 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72',
        'entrainement' => 'https://images.unsplash.com/photo-1517836357463-d25dfeac3438?auto=format&fit=crop&w=900&q=72',
        'salle' => 'https://images.unsplash.com/photo-1571902943202-507ec2618e8f?auto=format&fit=crop&w=900&q=72',
        'coach' => 'https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72',
    ];

    /** [nom, catégorie, description, photo] */
    private const ACTIVITIES = [
        ['Yoga', 'Bien-être', 'Postures, respiration et relaxation pour gagner en souplesse et en calme.', 'yoga'],
        ['Pilates', 'Bien-être', 'Renforcement des muscles profonds et travail de la posture.', 'yoga'],
        ['Stretching', 'Bien-être', 'Étirements guidés pour récupérer et prévenir les blessures.', 'yoga'],
        ['CrossFit', 'Haute intensité', 'Circuits fonctionnels variés, à haute intensité.', 'entrainement'],
        ['Musculation', 'Force', 'Travail guidé aux charges libres et aux machines.', 'muscu'],
        ['Boxe', 'Sport de combat', 'Technique, cardio-boxe et travail au sac.', 'boxe'],
        ['Cardio', 'Cardio', 'Endurance et dépense énergétique sur des formats courts.', 'salle'],
        ['Spinning', 'Cardio', 'Vélo en salle en musique, intervalles et montées.', 'salle'],
        ['Running', 'Plein air', 'Sorties en groupe, fractionné et préparation de courses.', 'course'],
        ['Zumba', 'Danse', 'Fitness dansé sur des rythmes latins.', 'entrainement'],
    ];

    /** [nom, adresse, code postal, commune, latitude, longitude] — coordonnées approximatives. */
    private const GYMS = [
        ['FitBook Flagey', 'Place Eugène Flagey 18', '1050', 'Ixelles', '50.827500', '4.372000'],
        ['FitBook Parvis', 'Parvis de Saint-Gilles 31', '1060', 'Saint-Gilles', '50.827600', '4.346000'],
        ['FitBook Jourdan', 'Place Jourdan 12', '1040', 'Etterbeek', '50.837000', '4.379000'],
        ['FitBook Colignon', 'Place Colignon 5', '1030', 'Schaerbeek', '50.866000', '4.378000'],
        ['FitBook Saint-Job', 'Place de Saint-Job 22', '1180', 'Uccle', '50.793000', '4.365000'],
        ['FitBook Woluwe', 'Rue Saint-Lambert 200', '1200', 'Woluwe-Saint-Lambert', '50.846000', '4.432000'],
        ['FitBook Vaillance', 'Place de la Vaillance 8', '1070', 'Anderlecht', '50.836000', '4.308000'],
        ['FitBook Sainte-Catherine', 'Place Sainte-Catherine 40', '1000', 'Bruxelles', '50.851000', '4.348000'],
        ['FitBook Saint-Denis', 'Place Saint-Denis 3', '1190', 'Forest', '50.811000', '4.320000'],
        ['FitBook Reine Astrid', 'Place Reine Astrid 14', '1090', 'Jette', '50.879000', '4.327000'],
    ];

    private Generator $faker;

    /** @var array<string, string> Hash par rôle, calculé une seule fois (le hachage est volontairement lent). */
    private array $hashes = [];

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->faker = Factory::create('fr_BE');
        $this->faker->seed(self::SEED);

        $admin = $this->createUser($manager, 'admin@fitbook.test', User::ROLE_ADMIN, 'Admin', 'FitBook');
        $managers = $this->createManagers($manager);
        $gyms = $this->createGyms($manager, $managers);
        $activities = $this->createActivities($manager);
        $coaches = $this->createCoaches($manager);
        $athletes = $this->createAthletes($manager);

        $this->createAvailabilities($manager, $coaches, $gyms);
        $upcoming = $this->createSessions($manager, $activities, $gyms, $coaches, self::UPCOMING_SESSIONS, past: false);
        $past = $this->createSessions($manager, $activities, $gyms, $coaches, self::PAST_SESSIONS, past: true);

        $finished = $this->createReservations($manager, $upcoming, $past, $athletes);
        $this->createReviews($manager, $finished);
        $this->createFavorites($manager, $athletes, $gyms, $coaches, $upcoming);
        $this->createNotifications($manager, $athletes, $admin);

        $manager->flush();
    }

    // -------------------------------------------------------------------------
    // Comptes
    // -------------------------------------------------------------------------

    private function createUser(ObjectManager $manager, string $email, string $role, string $firstName, string $lastName): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setPhone($this->faker->phoneNumber())
            ->setRoles([$role])
            ->setIsVerified(true)
            ->setCreatedAt(\DateTimeImmutable::createFromMutable($this->faker->dateTimeBetween('-6 months', '-1 month')));

        $this->hashes[$role] ??= $this->passwordHasher->hashPassword($user, self::PASSWORDS[$role]);
        $user->setPassword($this->hashes[$role]);
        $manager->persist($user);

        return $user;
    }

    /** @return list<User> */
    private function createManagers(ObjectManager $manager): array
    {
        $managers = [];
        for ($i = 1; $i <= 5; ++$i) {
            $managers[] = $this->createUser($manager, "gestionnaire{$i}@fitbook.test", User::ROLE_GESTIONNAIRE, $this->faker->firstName(), $this->faker->lastName());
        }

        return $managers;
    }

    /** @return list<CoachProfile> */
    private function createCoaches(ObjectManager $manager): array
    {
        $specialities = array_column(self::ACTIVITIES, 0);
        $coaches = [];

        for ($i = 1; $i <= 15; ++$i) {
            $user = $this->createUser($manager, "coach{$i}@fitbook.test", User::ROLE_COACH, $this->faker->firstName(), $this->faker->lastName());
            $user->setAvatar(self::PHOTO['coach']);

            $mine = $this->faker->randomElements($specialities, $this->faker->numberBetween(1, 3));
            $profile = (new CoachProfile())
                ->setUser($user)
                ->setSpecialities($mine)
                ->setBio(sprintf('Coach diplômé·e, spécialisé·e en %s. J’accompagne tous les niveaux avec un suivi personnalisé.', implode(', ', $mine)))
                ->setExperienceYears($this->faker->numberBetween(1, 15))
                ->setHourlyRate(number_format($this->faker->numberBetween(25, 60), 2, '.', ''))
                ->setCity($this->faker->randomElement(array_column(self::GYMS, 3)))
                ->setCertifications($this->faker->randomElement(['BPJEPS AF', 'Brevet ADEPS', 'Moniteur sportif initiateur', 'Certification Pilates Mat', 'RYT 200 Yoga']));
            $manager->persist($profile);
            $coaches[] = $profile;
        }

        return $coaches;
    }

    /**
     * 40 sportifs : le 39e n'a pas confirmé son adresse et le 40e est bloqué (états de démonstration).
     *
     * @return list<User> sportifs actifs uniquement
     */
    private function createAthletes(ObjectManager $manager): array
    {
        $active = [];
        for ($i = 1; $i <= 40; ++$i) {
            $user = $this->createUser($manager, "sportif{$i}@fitbook.test", User::ROLE_SPORTIF, $this->faker->firstName(), $this->faker->lastName());
            if ($i === 39) {
                $user->setIsVerified(false);
                continue;
            }
            if ($i === 40) {
                $user->setIsBlocked(true);
                continue;
            }
            $active[] = $user;
        }

        return $active;
    }

    // -------------------------------------------------------------------------
    // Catalogue
    // -------------------------------------------------------------------------

    /**
     * @param list<User> $managers
     *
     * @return list<Gym>
     */
    private function createGyms(ObjectManager $manager, array $managers): array
    {
        $gyms = [];
        foreach (self::GYMS as $index => [$name, $address, $postalCode, $city, $latitude, $longitude]) {
            $gym = (new Gym())
                ->setManager($managers[$index % \count($managers)])
                ->setName($name)
                ->setDescription(sprintf('Salle de sport à %s : plateau de musculation, studio de cours collectifs et vestiaires.', $city))
                ->setAddress($address)
                ->setPostalCode($postalCode)
                ->setCity($city)
                ->setLatitude($latitude)
                ->setLongitude($longitude)
                ->setPhone($this->faker->phoneNumber())
                ->setEmail(sprintf('contact.%s@fitbook.test', strtolower(str_replace([' ', '-'], '', $city))))
                ->setOpeningHours([
                    'lundi' => '07:00-22:00', 'mardi' => '07:00-22:00', 'mercredi' => '07:00-22:00',
                    'jeudi' => '07:00-22:00', 'vendredi' => '07:00-21:00', 'samedi' => '09:00-18:00', 'dimanche' => '09:00-14:00',
                ])
                ->setPhotos([self::PHOTO['salle'], self::PHOTO['muscu'], self::PHOTO['entrainement']]);
            $manager->persist($gym);
            $gyms[] = $gym;
        }

        return $gyms;
    }

    /** @return list<Activity> */
    private function createActivities(ObjectManager $manager): array
    {
        $activities = [];
        foreach (self::ACTIVITIES as [$name, $category, $description, $photo]) {
            $activity = (new Activity())
                ->setName($name)
                ->setCategory($category)
                ->setDescription($description)
                ->setIcon(self::PHOTO[$photo]);
            $manager->persist($activity);
            $activities[] = $activity;
        }

        return $activities;
    }

    /**
     * Créneaux récurrents : 2 par coach et 2 par salle.
     *
     * @param list<CoachProfile> $coaches
     * @param list<Gym>          $gyms
     */
    private function createAvailabilities(ObjectManager $manager, array $coaches, array $gyms): void
    {
        foreach ([...$coaches, ...$gyms] as $owner) {
            foreach ($this->faker->randomElements([1, 2, 3, 4, 5, 6], 2) as $day) {
                $startHour = $this->faker->numberBetween(7, 17);
                $slot = (new Availability())
                    ->setDayOfWeek($day)
                    ->setStartTime(new \DateTimeImmutable(sprintf('%02d:00', $startHour)))
                    ->setEndTime(new \DateTimeImmutable(sprintf('%02d:00', $startHour + $this->faker->numberBetween(2, 4))))
                    ->setIsRecurring(true);
                $owner instanceof Gym ? $slot->setGym($owner) : $slot->setCoach($owner);
                $manager->persist($slot);
            }
        }
    }

    /**
     * Séances réparties sur les 4 prochaines semaines (ou les 3 dernières si $past).
     * Environ 60 % ont une salle et un coach, 25 % une salle seule, 15 % un coach seul (plein air).
     * Parmi les séances à venir, 4 sont annulées (états de démonstration).
     *
     * @param list<Activity>     $activities
     * @param list<Gym>          $gyms
     * @param list<CoachProfile> $coaches
     *
     * @return list<Session>
     */
    private function createSessions(ObjectManager $manager, array $activities, array $gyms, array $coaches, int $count, bool $past): array
    {
        $local = new \DateTimeZone(self::LOCAL_TIMEZONE);
        $utc = new \DateTimeZone('UTC');
        $today = new \DateTimeImmutable('today', $local);
        $sessions = [];

        for ($i = 0; $i < $count; ++$i) {
            $activity = $this->faker->randomElement($activities);
            $days = $past ? -$this->faker->numberBetween(1, 21) : $this->faker->numberBetween(1, 28);
            $hour = $this->faker->randomElement([7, 8, 9, 10, 12, 12, 17, 18, 18, 19, 19, 20]);
            $minute = $this->faker->randomElement([0, 0, 30]);
            $duration = $this->faker->randomElement([45, 60, 60, 75, 90]);

            // Heure de Bruxelles, convertie en UTC pour le stockage.
            $startAt = $today->modify("{$days} days")->setTime($hour, $minute)->setTimezone($utc);
            $host = $this->faker->numberBetween(1, 100);

            $session = (new Session())
                ->setActivity($activity)
                ->setGym($host <= 85 ? $this->faker->randomElement($gyms) : null)
                ->setCoach($host <= 60 || $host > 85 ? $this->faker->randomElement($coaches) : null)
                ->setTitle(sprintf('%s %s', $activity->getName(), $this->faker->randomElement(['découverte', 'intensif', 'du matin', 'express', 'en groupe', 'avancé'])))
                ->setDescription($activity->getDescription())
                ->setStartAt($startAt)
                ->setEndAt($startAt->modify("+{$duration} minutes"))
                ->setCapacity($this->faker->randomElement([8, 10, 12, 15, 20]))
                ->setPrice(number_format($this->faker->randomElement([0, 10, 12, 15, 18, 20, 25, 30]), 2, '.', ''))
                ->setLevel($this->faker->randomElement(SessionLevel::cases()))
                ->setStatus(!$past && $i < 4 ? SessionStatus::Annulee : SessionStatus::Active);
            $manager->persist($session);
            $sessions[] = $session;
        }

        return $sessions;
    }

    // -------------------------------------------------------------------------
    // Réservations, avis, favoris, notifications
    // -------------------------------------------------------------------------

    /**
     * Réservations sans doublon ni dépassement de capacité.
     * - séances à venir : remplissage aléatoire, 2 séances complètes, quelques annulations ;
     * - séances passées : réservations « terminées » (elles ouvrent le droit aux avis).
     *
     * @param list<Session> $upcoming
     * @param list<Session> $past
     * @param list<User>    $athletes
     *
     * @return list<Reservation> réservations terminées
     */
    private function createReservations(ObjectManager $manager, array $upcoming, array $past, array $athletes): array
    {
        $finished = [];
        $fullSessions = 0;

        foreach ([...$upcoming, ...$past] as $session) {
            if ($session->getStatus() === SessionStatus::Annulee) {
                continue;
            }
            $isPast = \in_array($session, $past, true);
            $capacity = $session->getCapacity();

            // Deux séances à venir affichées complètes, pour tester le refus 409.
            $makeFull = !$isPast && $fullSessions < 2 && $capacity <= 10;
            $fullSessions += $makeFull ? 1 : 0;
            $count = $makeFull ? $capacity : $this->faker->numberBetween(0, (int) ceil($capacity * 0.8));

            // randomElements tire sans remise : jamais deux réservations du même sportif sur une séance.
            foreach ($this->faker->randomElements($athletes, min($count, \count($athletes))) as $athlete) {
                // Environ 1 réservation à venir sur 12 a été annulée par le sportif.
                $cancelled = !$isPast && !$makeFull && $this->faker->numberBetween(1, 12) === 1;
                $status = match (true) {
                    $isPast => ReservationStatus::Terminee,
                    $cancelled => ReservationStatus::Annulee,
                    default => ReservationStatus::Confirmee,
                };

                $reservation = (new Reservation())
                    ->setUser($athlete)
                    ->setSession($session)
                    ->setStatus($status)
                    ->setCreatedAt($session->getStartAt()->modify(sprintf('-%d days', $this->faker->numberBetween(1, 10))))
                    ->setCancelledAt($cancelled ? new \DateTimeImmutable('-1 day') : null);
                $manager->persist($reservation);

                if ($status === ReservationStatus::Terminee) {
                    $finished[] = $reservation;
                }
            }
        }

        return $finished;
    }

    /**
     * Avis uniquement sur une cible liée à une réservation terminée (RG-06), un par cible (RG-07).
     * Une partie est validée par l'admin ; deux avis négatifs sont masqués (démonstration de la modération).
     *
     * @param list<Reservation> $finished
     */
    private function createReviews(ObjectManager $manager, array $finished): void
    {
        $done = [];
        $hidden = 0;
        $comments = [
            5 => ['Excellente séance, je recommande !', 'Coach au top et ambiance motivante.', 'Parfait pour progresser.'],
            4 => ['Très bonne séance, un peu chargée.', 'Bon rythme, vestiaires propres.', 'Je reviendrai.'],
            3 => ['Correct, mais la salle était bondée.', 'Séance sympa sans plus.'],
            2 => ['Trop de monde pour un bon suivi.', 'Démarrage en retard.'],
        ];

        foreach ($finished as $reservation) {
            if ($this->faker->numberBetween(1, 100) > 60) {
                continue;
            }
            $session = $reservation->getSession();
            $target = $this->faker->randomElement(array_values(array_filter([$session, $session->getGym(), $session->getCoach()])));

            // Un seul avis par auteur et par cible.
            $key = spl_object_id($reservation->getUser()) . '|' . spl_object_id($target);
            if (isset($done[$key])) {
                continue;
            }
            $done[$key] = true;

            $rating = $this->faker->randomElement([5, 5, 4, 4, 4, 3, 2]);
            $review = (new Review())
                ->setAuthor($reservation->getUser())
                ->setTarget($target)
                ->setRating($rating)
                ->setComment($this->faker->randomElement($comments[$rating]))
                ->setCreatedAt($session->getEndAt()->modify('+1 day'))
                ->setIsModerated($this->faker->boolean(40));

            if ($rating <= 2 && $hidden < 2) {
                $review->setIsVisible(false)->setIsModerated(true);
                ++$hidden;
            }
            $manager->persist($review);
        }
    }

    /**
     * 0 à 4 favoris par sportif, sans doublon.
     *
     * @param list<User>         $athletes
     * @param list<Gym>          $gyms
     * @param list<CoachProfile> $coaches
     * @param list<Session>      $sessions
     */
    private function createFavorites(ObjectManager $manager, array $athletes, array $gyms, array $coaches, array $sessions): void
    {
        $candidates = [...$gyms, ...$coaches, ...$sessions];
        foreach ($athletes as $athlete) {
            foreach ($this->faker->randomElements($candidates, $this->faker->numberBetween(0, 4)) as $target) {
                $manager->persist((new Favorite())->setUser($athlete)->setTarget($target));
            }
        }
    }

    /** @param list<User> $athletes */
    private function createNotifications(ObjectManager $manager, array $athletes, User $admin): void
    {
        foreach (\array_slice($athletes, 0, 10) as $athlete) {
            $manager->persist((new Notification())
                ->setUser($athlete)
                ->setType('bienvenue')
                ->setMessage('Bienvenue sur FitBook ! Découvrez les séances près de chez vous.'));
        }
        $manager->persist((new Notification())
            ->setUser($admin)
            ->setType('moderation')
            ->setMessage('Des avis sont en attente de modération.'));
    }
}
