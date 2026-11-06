# Diagramme de classes — FitBook

> Version 1.0 — 5 novembre 2026 — statut : **à valider par les formateurs**
> À reproduire dans **StarUML**. Cohérent avec le [MLD](mld.md).

Deux vues :
1. **Domaine** : les entités Doctrine, leurs attributs et leurs associations.
2. **Architecture backend** : comment contrôleurs, services, voters et repositories collaborent.

---

## 1. Modèle du domaine (entités Doctrine)

```mermaid
classDiagram
    direction LR

    class User {
        -int id
        -string email
        -string password
        -string firstName
        -string lastName
        -string phone
        -string avatar
        -array roles
        -bool isVerified
        -bool isBlocked
        -DateTimeImmutable createdAt
        +getUserIdentifier() string
        +getRoles() array
        +getFullName() string
    }

    class CoachProfile {
        -int id
        -string bio
        -array specialities
        -int experienceYears
        -string hourlyRate
        -string city
        -string certifications
    }

    class Gym {
        -int id
        -string name
        -string description
        -string address
        -string city
        -string postalCode
        -string latitude
        -string longitude
        -string phone
        -string email
        -array openingHours
        -array photos
        -DateTimeImmutable createdAt
    }

    class Activity {
        -int id
        -string name
        -string description
        -string icon
        -string category
    }

    class Session {
        -int id
        -string title
        -string description
        -DateTimeImmutable startAt
        -DateTimeImmutable endAt
        -int capacity
        -string price
        -SessionLevel level
        -SessionStatus status
        +getConfirmedCount() int
        +getRemainingPlaces() int
        +isBookable(DateTimeImmutable now) bool
    }

    class Availability {
        -int id
        -int dayOfWeek
        -DateTimeImmutable startTime
        -DateTimeImmutable endTime
        -bool isRecurring
        -DateTimeImmutable date
    }

    class Reservation {
        -int id
        -ReservationStatus status
        -DateTimeImmutable createdAt
        -DateTimeImmutable cancelledAt
        -string qrToken
        -DateTimeImmutable reminderSentAt
        +isCancellable(DateTimeImmutable now) bool
    }

    class Favorite {
        -int id
        -TargetType type
        -DateTimeImmutable createdAt
    }

    class Review {
        -int id
        -int rating
        -string comment
        -bool isModerated
        -bool isVisible
        -DateTimeImmutable createdAt
    }

    class Notification {
        -int id
        -string type
        -string message
        -bool isRead
        -DateTimeImmutable createdAt
    }

    class SessionLevel {
        <<enumeration>>
        DEBUTANT
        INTERMEDIAIRE
        AVANCE
    }
    class SessionStatus {
        <<enumeration>>
        ACTIVE
        ANNULEE
    }
    class ReservationStatus {
        <<enumeration>>
        CONFIRMEE
        ANNULEE
        TERMINEE
    }
    class TargetType {
        <<enumeration>>
        GYM
        COACH
        SESSION
    }

    User "1" -- "0..1" CoachProfile : possède
    User "1" -- "0..*" Gym : gère
    Activity "1" -- "0..*" Session : relève de
    Gym "0..1" -- "0..*" Session : accueille
    CoachProfile "0..1" -- "0..*" Session : anime
    User "1" -- "0..*" Reservation : effectue
    Session "1" -- "0..*" Reservation : concerne
    CoachProfile "0..1" -- "0..*" Availability : propose
    Gym "0..1" -- "0..*" Availability : propose
    User "1" -- "0..*" Favorite : ajoute
    Favorite "0..*" -- "0..1" Gym : cible
    Favorite "0..*" -- "0..1" CoachProfile : cible
    Favorite "0..*" -- "0..1" Session : cible
    User "1" -- "0..*" Review : rédige
    Review "0..*" -- "0..1" Gym : porte sur
    Review "0..*" -- "0..1" CoachProfile : porte sur
    Review "0..*" -- "0..1" Session : porte sur
    User "1" -- "0..*" Notification : reçoit

    Session ..> SessionLevel
    Session ..> SessionStatus
    Reservation ..> ReservationStatus
    Favorite ..> TargetType
```

Notes :
- `User` implémente `UserInterface` et `PasswordAuthenticatedUserInterface` (sécurité Symfony).
- Les montants (`price`, `hourlyRate`) et coordonnées sont typés `string` en PHP : c'est ainsi
  que Doctrine restitue les colonnes `DECIMAL`, sans perte de précision.
- `ResetPasswordRequest` (fournie par SymfonyCasts ResetPasswordBundle) n'est pas représentée.

## 2. Architecture backend

Requête type : **Contrôleur → (Validator / JSON Schema) → Voter → Service → Repository → MySQL**.

```mermaid
classDiagram
    direction TB

    class ReservationController {
        +create(Request) JsonResponse
        +cancel(int id) JsonResponse
        +mine() JsonResponse
    }
    class SessionController {
        +list() JsonResponse
        +show(int id) JsonResponse
        +create(Request) JsonResponse
        +update(int id, Request) JsonResponse
        +cancel(int id) JsonResponse
    }
    class SearchController {
        +search(Request) JsonResponse
    }
    class RegistrationController {
        +register(Request) JsonResponse
        +verifyEmail(Request) Response
        +resendVerification(Request) JsonResponse
    }

    class ReservationService {
        +reserve(User, Session) Reservation
        +cancel(User, Reservation) void
    }
    class SearchService {
        +search(SearchCriteria) PaginatedResult
    }
    class JsonSchemaValidator {
        +validate(array data, string schema) array
    }
    class FitBookMailer {
        +sendVerification(User, string url) EmailDeliveryStatus
        +sendReservationConfirmation(Reservation) EmailDeliveryStatus
        +sendReservationCancellation(Reservation) EmailDeliveryStatus
        +sendReminder(Reservation) EmailDeliveryStatus
    }

    class SessionVoter {
        <<Voter>>
        EDIT
        DELETE
        +voteOnAttribute() bool
    }
    class GymVoter {
        <<Voter>>
        EDIT
        DELETE
        +voteOnAttribute() bool
    }
    class ReservationVoter {
        <<Voter>>
        VIEW
        CANCEL
        +voteOnAttribute() bool
    }

    class ReservationRepository {
        +callReserveProcedure(int userId, int sessionId) int
        +callCancelProcedure(int reservationId, int userId) int
    }
    class GymRepository {
        +callStatsProcedure(int gymId) array
    }
    class SessionRepository {
        +search(SearchCriteria) PaginatedResult
    }

    ReservationController --> JsonSchemaValidator
    ReservationController --> ReservationVoter
    ReservationController --> ReservationService
    SessionController --> SessionVoter
    SearchController --> SearchService
    RegistrationController --> FitBookMailer
    ReservationService --> ReservationRepository
    ReservationService --> FitBookMailer
    SearchService --> SessionRepository
    SearchService --> GymRepository
```

| Couche | Responsabilité | Exemples |
|--------|----------------|----------|
| Contrôleur | Lit la requête, renvoie le JSON et le bon code HTTP | `ReservationController` |
| Validation | Symfony Validator (attributs sur les DTO/entités) et JSON Schema (`opis/json-schema`) | `JsonSchemaValidator` |
| Voter | Décide si l'utilisateur peut agir sur **cette** ressource (propriété) | `SessionVoter`, `GymVoter` |
| Service | Règles métier, transactions, e-mails | `ReservationService` |
| Repository | Requêtes Doctrine paramétrées et appels SQL natifs aux procédures stockées | `ReservationRepository` |

La liste complète des contrôleurs et des services sera établie en Phase 4. Cette vue fixe les
principes : aucune règle métier dans les contrôleurs, aucun SQL hors des repositories.
