# User stories — FitBook

> Version 1.0 — 5 novembre 2026 — statut : **à valider par les formateurs**

**Format :** *En tant que* [rôle], *je veux* [action] *afin de* [bénéfice].
**Priorité (MoSCoW) :** **M** = indispensable · **S** = important · **C** = bonus.
**Phase :** phase du plan où la story est livrée (voir [cahier des charges](cahier-des-charges.md) § 11).

Les critères d'acceptation sont écrits pour être vérifiables : ils servent de base aux
tests de la Phase 9 (`docs/liste-des-tests.md`).

---

## 1. Visiteur (non connecté)

### US-V01 — Rechercher une activité · M · Phase 6
*En tant que* visiteur, *je veux* rechercher des salles, des coachs ou des séances par mot-clé,
ville et date *afin de* trouver rapidement une offre qui me convient.

- La barre de recherche de l'accueil propose trois champs : **quoi**, **où**, **quand**.
- Valider la recherche ouvre `/recherche` avec les critères dans l'URL (lien partageable).
- Les résultats sont paginés ; une recherche sans résultat affiche un état vide explicite.

### US-V02 — Filtrer et trier les résultats · M · Phase 6
*En tant que* visiteur, *je veux* filtrer par type, activité, prix maximum, niveau et note minimale,
puis trier les résultats *afin de* comparer les offres.

- Chaque filtre modifié met à jour les résultats sans recharger la page.
- Le tri propose au moins : date, prix croissant, note décroissante.
- Les filtres actifs sont visibles et peuvent être réinitialisés en un clic.

### US-V03 — Voir les résultats sur une carte · C · Phase 8
*En tant que* visiteur, *je veux* afficher les salles sur une carte et chercher autour de moi
*afin de* choisir une salle proche.

- Un bouton bascule entre la vue liste et la vue carte (Leaflet).
- Le filtre « distance » (rayon en km) utilise ma position si je l'autorise.

### US-V04 — Consulter une fiche · M · Phase 6
*En tant que* visiteur, *je veux* consulter la fiche d'une salle, d'un coach ou d'une séance
*afin de* décider de réserver.

- La fiche affiche : photos, description, adresse ou spécialités, horaires, prochaines séances,
  note moyenne et avis visibles.
- Le bouton **Réserver** d'une séance complète est désactivé et indique « Complet ».
- Une fiche inexistante affiche une page « introuvable », pas une erreur technique.

### US-V05 — Créer un compte en choisissant mon rôle · M · Phase 3
*En tant que* visiteur, *je veux* créer un compte de sportif, de coach ou de gestionnaire
*afin d'*accéder aux fonctionnalités de mon profil.

- Le formulaire demande : prénom, nom, e-mail, téléphone, mot de passe, rôle.
- Le rôle « administrateur » n'est jamais proposé ; un rôle non autorisé envoyé à l'API est refusé.
- Les erreurs sont affichées sous chaque champ, côté client **et** côté serveur.
- Une adresse déjà utilisée est refusée (409) avec un message clair.

### US-V06 — Confirmer mon adresse e-mail · M · Phase 3
*En tant que* visiteur inscrit, *je veux* confirmer mon adresse par un lien reçu par e-mail
*afin d'*activer mon compte.

- Le lien est signé et expire ; un lien modifié ou expiré est refusé.
- Tant que l'adresse n'est pas confirmée, la connexion est refusée avec un message explicite.
- Je peux demander un nouveau lien.

### US-V07 — Réinitialiser mon mot de passe · M · Phase 3
*En tant qu'*utilisateur qui a oublié son mot de passe, *je veux* recevoir un lien de
réinitialisation *afin de* récupérer mon compte.

- La réponse est la même que l'adresse existe ou non (pas d'énumération des comptes).
- Le lien est à usage unique et expire.

---

## 2. Sportif (`ROLE_SPORTIF`)

### US-S01 — Me connecter · M · Phase 3
*En tant que* sportif, *je veux* me connecter avec mon e-mail et mon mot de passe
*afin d'*accéder à mon espace.

- Une connexion réussie renvoie un jeton JWT ; le front le joint à chaque appel API.
- Après plusieurs échecs rapprochés, les tentatives sont temporairement bloquées.
- Un jeton expiré renvoie vers la page de connexion, puis vers la page demandée.

### US-S02 — Réserver une séance · M · Phase 4/6
*En tant que* sportif, *je veux* réserver une séance *afin de* garantir ma place.

- La réservation est refusée (409) si la séance est complète ou si je l'ai déjà réservée.
- Deux réservations simultanées sur la dernière place n'aboutissent jamais toutes les deux.
- Une séance passée ou annulée ne peut pas être réservée.
- Je reçois un e-mail de confirmation.

### US-S03 — Annuler une réservation · M · Phase 4/6
*En tant que* sportif, *je veux* annuler ma réservation *afin de* libérer la place si je ne peux pas venir.

- L'annulation est possible jusqu'à **2 heures** avant le début ; au-delà, elle est refusée avec un message.
- Une fenêtre de confirmation s'affiche avant l'annulation.
- La place redevient disponible et je reçois un e-mail d'annulation.

### US-S04 — Consulter mes réservations · M · Phase 6
*En tant que* sportif, *je veux* voir mes réservations à venir et mon historique
*afin de* m'organiser.

- Deux onglets : **À venir** et **Historique** (terminées et annulées).
- Un calendrier personnel affiche mes séances à venir.

### US-S05 — Gérer mes favoris · S · Phase 6
*En tant que* sportif, *je veux* ajouter une salle, un coach ou une séance en favori
*afin de* les retrouver rapidement.

- Le bouton favori bascule (ajout / retrait) depuis les cartes et les fiches.
- La page « Mes favoris » les regroupe par type.

### US-S06 — Donner mon avis · S · Phase 4/6
*En tant que* sportif, *je veux* noter (1 à 5) et commenter une salle, un coach ou une séance
*afin de* partager mon expérience.

- L'avis n'est possible qu'après une réservation **terminée** liée à la cible.
- Un seul avis par cible ; je peux le modifier ou le supprimer.
- Le commentaire est affiché sans interprétation HTML (protection XSS).

### US-S07 — Gérer mon profil · M · Phase 6
*En tant que* sportif, *je veux* modifier mes informations et mon avatar *afin de* garder mon profil à jour.

- Seuls prénom, nom, téléphone et avatar sont modifiables ; l'e-mail et le rôle ne le sont pas.
- L'avatar accepte uniquement des images (JPEG, PNG, WebP) de taille limitée.

### US-S08 — Être rappelé avant ma séance · C · Phase 8
*En tant que* sportif, *je veux* recevoir un rappel 24 h avant ma séance *afin de* ne pas l'oublier.

- La commande `app:send-reminders` envoie un seul rappel par réservation confirmée.

### US-S09 — Présenter un QR code · C · Phase 8
*En tant que* sportif, *je veux* un QR code par réservation *afin de* la présenter à l'accueil.

---

## 3. Coach (`ROLE_COACH`)

### US-C01 — Gérer mon profil professionnel · M · Phase 7
*En tant que* coach, *je veux* renseigner ma bio, mes spécialités, mon expérience, mon tarif
horaire, ma ville et mes certifications *afin d'*être trouvé par les sportifs.

- Ces informations apparaissent sur ma fiche publique `/coachs/:id`.

### US-C02 — Gérer mes séances · M · Phase 4/7
*En tant que* coach, *je veux* créer, modifier et supprimer mes séances *afin de* proposer mon offre.

- Champs : titre, description, activité, salle (facultative), début, fin, capacité, prix, niveau.
- La fin doit être postérieure au début, la capacité ≥ 1, le prix ≥ 0.
- Je ne peux ni voir en édition ni modifier la séance d'un autre coach (403).
- Annuler une séance prévient par e-mail les sportifs inscrits.

### US-C03 — Gérer mes disponibilités · S · Phase 7
*En tant que* coach, *je veux* déclarer mes créneaux récurrents ou ponctuels
*afin d'*organiser mon planning.

- Un créneau récurrent porte sur un jour de la semaine ; un créneau ponctuel sur une date.
- L'heure de fin est postérieure à l'heure de début.

### US-C04 — Voir mon calendrier · S · Phase 7
*En tant que* coach, *je veux* un calendrier (FullCalendar) de mes séances et disponibilités
*afin d'*avoir une vue d'ensemble.

### US-C05 — Voir les inscrits d'une séance · M · Phase 7
*En tant que* coach, *je veux* la liste des réservations de chacune de mes séances
*afin de* préparer mes cours.

### US-C06 — Consulter mes avis et statistiques · S · Phase 7
*En tant que* coach, *je veux* voir les avis reçus et quelques statistiques (réservations, note moyenne)
*afin d'*améliorer mon offre.

---

## 4. Gestionnaire de salle (`ROLE_GESTIONNAIRE`)

### US-G01 — Gérer mes salles · M · Phase 4/7
*En tant que* gestionnaire, *je veux* créer et modifier les informations de mes salles
(nom, adresse, coordonnées, horaires d'ouverture, photos) *afin de* les présenter.

- Je ne peux modifier que les salles dont je suis le gestionnaire (403 sinon).

### US-G02 — Gérer les cours de mes salles · M · Phase 4/7
*En tant que* gestionnaire, *je veux* créer, modifier et annuler les séances de mes salles,
avec leurs horaires et capacités, *afin de* remplir mon planning.

### US-G03 — Gérer les disponibilités de mes salles · S · Phase 7
*En tant que* gestionnaire, *je veux* déclarer les créneaux disponibles de mes salles
*afin de* faciliter l'organisation des cours.

### US-G04 — Suivre les réservations · M · Phase 7
*En tant que* gestionnaire, *je veux* voir les réservations des séances de mes salles
*afin de* prévoir l'accueil.

### US-G05 — Consulter le tableau de bord · M · Phase 7
*En tant que* gestionnaire, *je veux* voir pour chaque salle le nombre de réservations,
le taux de remplissage et la note moyenne *afin de* piloter mon activité.

- Les chiffres proviennent de la procédure stockée `sp_stats_salle`.
- Un graphique (Chart.js) présente l'évolution des réservations.

### US-G06 — Consulter les avis de mes salles · S · Phase 7
*En tant que* gestionnaire, *je veux* lire les avis laissés sur mes salles *afin d'*en tenir compte.

---

## 5. Administrateur (`ROLE_ADMIN`)

### US-A01 — Voir le tableau de bord global · M · Phase 7
*En tant qu'*administrateur, *je veux* une vue d'ensemble (utilisateurs par rôle, salles,
séances, réservations, avis en attente) *afin de* surveiller la plateforme.

### US-A02 — Gérer les utilisateurs · M · Phase 4/7
*En tant qu'*administrateur, *je veux* lister, rechercher, modifier le rôle et bloquer
ou débloquer un utilisateur *afin de* faire respecter les règles.

- Un compte bloqué ne peut plus se connecter.
- Je ne peux pas retirer mon propre rôle administrateur.

### US-A03 — Gérer le catalogue · M · Phase 4/7
*En tant qu'*administrateur, *je veux* gérer toutes les salles, coachs, activités et
réservations *afin de* corriger les données.

### US-A04 — Modérer les avis · M · Phase 4/7
*En tant qu'*administrateur, *je veux* valider ou masquer un avis *afin d'*écarter les contenus abusifs.

- Un avis masqué disparaît des fiches et n'entre plus dans la note moyenne.
- La liste propose un filtre « non modérés ».

---

## 6. Récapitulatif

| Rôle | M | S | C | Total |
|------|---|---|---|-------|
| Visiteur | 6 | 0 | 1 | 7 |
| Sportif | 5 | 2 | 2 | 9 |
| Coach | 3 | 3 | 0 | 6 |
| Gestionnaire | 4 | 2 | 0 | 6 |
| Administrateur | 4 | 0 | 0 | 4 |
| **Total** | **22** | **7** | **3** | **32** |
