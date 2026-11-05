import { inject } from '@angular/core';
import { Router, Routes } from '@angular/router';
import { authGuard, invitesGuard, roleGuard } from './core/auth/guards';
import { LienEspace } from './espaces/espace-layout';

/*
 * Toutes les pages sont chargées à la demande (loadComponent) : le premier affichage ne
 * télécharge que la coquille et la page ouverte. Les espaces sont protégés par des guards ;
 * les droits réels restent vérifiés par l'API (rôles Symfony et Voters).
 */

const ESPACE_SPORTIF: LienEspace[] = [
  { chemin: '/mon-espace', libelle: 'Tableau de bord', exact: true },
  { chemin: '/mon-espace/reservations', libelle: 'Mes réservations' },
  { chemin: '/mon-espace/favoris', libelle: 'Mes favoris' },
  { chemin: '/mon-espace/avis', libelle: 'Mes avis' },
  { chemin: '/mon-espace/notifications', libelle: 'Notifications' },
  { chemin: '/mon-espace/profil', libelle: 'Mon profil' },
];

const ESPACE_COACH: LienEspace[] = [
  { chemin: '/coach', libelle: 'Tableau de bord', exact: true },
  { chemin: '/coach/seances', libelle: 'Mes séances' },
  { chemin: '/coach/calendrier', libelle: 'Calendrier' },
  { chemin: '/coach/disponibilites', libelle: 'Disponibilités' },
  { chemin: '/coach/avis', libelle: 'Avis reçus' },
  { chemin: '/coach/profil', libelle: 'Profil professionnel' },
  { chemin: '/mon-espace/profil', libelle: 'Mon compte' },
];

const ESPACE_GESTIONNAIRE: LienEspace[] = [
  { chemin: '/gestionnaire', libelle: 'Tableau de bord', exact: true },
  { chemin: '/gestionnaire/salles', libelle: 'Mes salles' },
  { chemin: '/gestionnaire/seances', libelle: 'Cours et séances' },
  { chemin: '/gestionnaire/calendrier', libelle: 'Calendrier' },
  { chemin: '/gestionnaire/disponibilites', libelle: 'Disponibilités' },
  { chemin: '/gestionnaire/reservations', libelle: 'Réservations' },
  { chemin: '/gestionnaire/avis', libelle: 'Avis' },
  { chemin: '/mon-espace/profil', libelle: 'Mon compte' },
];

const ESPACE_ADMIN: LienEspace[] = [
  { chemin: '/admin', libelle: 'Tableau de bord', exact: true },
  { chemin: '/admin/utilisateurs', libelle: 'Utilisateurs' },
  { chemin: '/admin/avis', libelle: 'Modération des avis' },
  { chemin: '/admin/reservations', libelle: 'Réservations' },
  { chemin: '/admin/seances', libelle: 'Séances' },
  { chemin: '/admin/salles', libelle: 'Salles' },
  { chemin: '/admin/activites', libelle: 'Activités' },
];

const layout = () => import('./espaces/espace-layout').then(m => m.EspaceLayoutComponent);

export const routes: Routes = [
  // --- Pages publiques -------------------------------------------------------
  { path: '', title: 'FitBook — Réservez votre sport', loadComponent: () => import('./pages/accueil/accueil').then(m => m.AccueilComponent) },
  { path: 'recherche', title: 'Rechercher — FitBook', loadComponent: () => import('./pages/recherche/recherche').then(m => m.RechercheComponent) },
  { path: 'seances/:id', title: 'Séance — FitBook', loadComponent: () => import('./pages/seance-detail/seance-detail').then(m => m.SeanceDetailComponent) },
  { path: 'salles/:id', title: 'Salle — FitBook', loadComponent: () => import('./pages/salle-detail/salle-detail').then(m => m.SalleDetailComponent) },
  { path: 'coachs/:id', title: 'Coach — FitBook', loadComponent: () => import('./pages/coach-detail/coach-detail').then(m => m.CoachDetailComponent) },
  { path: 'activites', title: 'Activités — FitBook', loadComponent: () => import('./pages/activites/activites').then(m => m.ActivitesComponent) },
  { path: 'tarifs', title: 'Tarifs — FitBook', loadComponent: () => import('./pages/tarifs/tarifs').then(m => m.TarifsComponent) },

  // --- Compte ----------------------------------------------------------------
  { path: 'connexion', title: 'Connexion — FitBook', canActivate: [invitesGuard], loadComponent: () => import('./pages/connexion/connexion').then(m => m.ConnexionComponent) },
  { path: 'inscription', title: 'Inscription — FitBook', canActivate: [invitesGuard], loadComponent: () => import('./pages/inscription/inscription').then(m => m.InscriptionComponent) },
  { path: 'verification-email', title: 'Vérification de l’adresse — FitBook', loadComponent: () => import('./pages/verification-email/verification-email').then(m => m.VerificationEmailComponent) },
  { path: 'mot-de-passe-oublie', title: 'Mot de passe oublié — FitBook', loadComponent: () => import('./pages/mot-de-passe-oublie/mot-de-passe-oublie').then(m => m.MotDePasseOublieComponent) },
  { path: 'reinitialiser-mot-de-passe', title: 'Nouveau mot de passe — FitBook', loadComponent: () => import('./pages/reinitialiser-mot-de-passe/reinitialiser-mot-de-passe').then(m => m.ReinitialiserMotDePasseComponent) },

  // --- Espace sportif (tout compte connecté) ---------------------------------
  {
    path: 'mon-espace',
    canActivate: [authGuard],
    loadComponent: layout,
    data: { titre: 'Mon espace', liens: ESPACE_SPORTIF },
    children: [
      { path: '', title: 'Mon espace — FitBook', loadComponent: () => import('./espaces/sportif/tableau/tableau').then(m => m.TableauSportifComponent) },
      { path: 'reservations', title: 'Mes réservations — FitBook', loadComponent: () => import('./espaces/sportif/reservations/reservations').then(m => m.MesReservationsComponent) },
      { path: 'reservations/:id', title: 'Ma réservation — FitBook', loadComponent: () => import('./espaces/sportif/reservation-detail/reservation-detail').then(m => m.ReservationDetailComponent) },
      { path: 'favoris', title: 'Mes favoris — FitBook', loadComponent: () => import('./espaces/sportif/favoris/favoris').then(m => m.MesFavorisComponent) },
      { path: 'avis', title: 'Mes avis — FitBook', loadComponent: () => import('./espaces/sportif/avis/avis').then(m => m.MesAvisComponent) },
      { path: 'notifications', title: 'Notifications — FitBook', loadComponent: () => import('./espaces/sportif/notifications/notifications').then(m => m.NotificationsComponent) },
      { path: 'profil', title: 'Mon profil — FitBook', loadComponent: () => import('./espaces/sportif/profil/profil').then(m => m.ProfilComponent) },
    ],
  },

  // --- Espace coach ------------------------------------------------------------
  {
    path: 'coach',
    canActivate: [roleGuard('coach')],
    loadComponent: layout,
    data: { titre: 'Espace coach', liens: ESPACE_COACH, espace: 'coach' },
    children: [
      { path: '', title: 'Espace coach — FitBook', loadComponent: () => import('./espaces/coach/tableau/tableau').then(m => m.TableauCoachComponent) },
      { path: 'seances', title: 'Mes séances — FitBook', loadComponent: () => import('./espaces/commun/seances-liste/seances-liste').then(m => m.SeancesListeComponent) },
      { path: 'seances/nouvelle', title: 'Nouvelle séance — FitBook', loadComponent: () => import('./espaces/commun/seance-form/seance-form').then(m => m.SeanceFormComponent) },
      { path: 'seances/:id', title: 'Modifier la séance — FitBook', loadComponent: () => import('./espaces/commun/seance-form/seance-form').then(m => m.SeanceFormComponent) },
      { path: 'seances/:id/inscrits', title: 'Inscrits — FitBook', loadComponent: () => import('./espaces/commun/inscrits/inscrits').then(m => m.InscritsComponent) },
      { path: 'calendrier', title: 'Calendrier — FitBook', loadComponent: () => import('./espaces/commun/calendrier-espace/calendrier-espace').then(m => m.CalendrierEspaceComponent) },
      { path: 'disponibilites', title: 'Disponibilités — FitBook', loadComponent: () => import('./espaces/commun/disponibilites/disponibilites').then(m => m.DisponibilitesComponent) },
      { path: 'avis', title: 'Avis reçus — FitBook', loadComponent: () => import('./espaces/commun/avis-recus/avis-recus').then(m => m.AvisRecusComponent) },
      { path: 'profil', title: 'Profil professionnel — FitBook', loadComponent: () => import('./espaces/coach/profil-pro/profil-pro').then(m => m.ProfilProComponent) },
    ],
  },

  // --- Espace gestionnaire --------------------------------------------------
  {
    path: 'gestionnaire',
    canActivate: [roleGuard('gestionnaire')],
    loadComponent: layout,
    data: { titre: 'Espace gestionnaire', liens: ESPACE_GESTIONNAIRE, espace: 'gestionnaire' },
    children: [
      { path: '', title: 'Espace gestionnaire — FitBook', loadComponent: () => import('./espaces/gestionnaire/tableau/tableau').then(m => m.TableauGestionnaireComponent) },
      { path: 'salles', title: 'Mes salles — FitBook', loadComponent: () => import('./espaces/gestionnaire/salles/salles').then(m => m.MesSallesComponent) },
      { path: 'salles/nouvelle', title: 'Nouvelle salle — FitBook', loadComponent: () => import('./espaces/commun/salle-form/salle-form').then(m => m.SalleFormComponent) },
      { path: 'salles/:id', title: 'Modifier la salle — FitBook', loadComponent: () => import('./espaces/commun/salle-form/salle-form').then(m => m.SalleFormComponent) },
      { path: 'seances', title: 'Cours et séances — FitBook', loadComponent: () => import('./espaces/commun/seances-liste/seances-liste').then(m => m.SeancesListeComponent) },
      { path: 'seances/nouvelle', title: 'Nouvelle séance — FitBook', loadComponent: () => import('./espaces/commun/seance-form/seance-form').then(m => m.SeanceFormComponent) },
      { path: 'seances/:id', title: 'Modifier la séance — FitBook', loadComponent: () => import('./espaces/commun/seance-form/seance-form').then(m => m.SeanceFormComponent) },
      { path: 'seances/:id/inscrits', title: 'Inscrits — FitBook', loadComponent: () => import('./espaces/commun/inscrits/inscrits').then(m => m.InscritsComponent) },
      { path: 'calendrier', title: 'Calendrier — FitBook', loadComponent: () => import('./espaces/commun/calendrier-espace/calendrier-espace').then(m => m.CalendrierEspaceComponent) },
      { path: 'disponibilites', title: 'Disponibilités — FitBook', loadComponent: () => import('./espaces/commun/disponibilites/disponibilites').then(m => m.DisponibilitesComponent) },
      { path: 'reservations', title: 'Réservations — FitBook', loadComponent: () => import('./espaces/gestionnaire/reservations/reservations').then(m => m.ReservationsGestionnaireComponent) },
      { path: 'avis', title: 'Avis — FitBook', loadComponent: () => import('./espaces/commun/avis-recus/avis-recus').then(m => m.AvisRecusComponent) },
    ],
  },

  // --- Administration -------------------------------------------------------
  {
    path: 'admin',
    canActivate: [roleGuard('admin')],
    loadComponent: layout,
    data: { titre: 'Administration', liens: ESPACE_ADMIN, espace: 'admin' },
    children: [
      { path: '', title: 'Administration — FitBook', loadComponent: () => import('./espaces/admin/tableau/tableau').then(m => m.TableauAdminComponent) },
      { path: 'utilisateurs', title: 'Utilisateurs — FitBook', loadComponent: () => import('./espaces/admin/utilisateurs/utilisateurs').then(m => m.UtilisateursComponent) },
      { path: 'avis', title: 'Modération — FitBook', loadComponent: () => import('./espaces/admin/moderation/moderation').then(m => m.ModerationComponent) },
      { path: 'reservations', title: 'Réservations — FitBook', loadComponent: () => import('./espaces/admin/reservations/reservations').then(m => m.ReservationsAdminComponent) },
      { path: 'seances', title: 'Séances — FitBook', loadComponent: () => import('./espaces/commun/seances-liste/seances-liste').then(m => m.SeancesListeComponent) },
      { path: 'seances/nouvelle', title: 'Nouvelle séance — FitBook', loadComponent: () => import('./espaces/commun/seance-form/seance-form').then(m => m.SeanceFormComponent) },
      { path: 'seances/:id', title: 'Modifier la séance — FitBook', loadComponent: () => import('./espaces/commun/seance-form/seance-form').then(m => m.SeanceFormComponent) },
      { path: 'seances/:id/inscrits', title: 'Inscrits — FitBook', loadComponent: () => import('./espaces/commun/inscrits/inscrits').then(m => m.InscritsComponent) },
      { path: 'salles', title: 'Salles — FitBook', loadComponent: () => import('./espaces/admin/salles/salles').then(m => m.SallesAdminComponent) },
      { path: 'salles/nouvelle', title: 'Nouvelle salle — FitBook', loadComponent: () => import('./espaces/commun/salle-form/salle-form').then(m => m.SalleFormComponent) },
      { path: 'salles/:id', title: 'Modifier la salle — FitBook', loadComponent: () => import('./espaces/commun/salle-form/salle-form').then(m => m.SalleFormComponent) },
      { path: 'activites', title: 'Activités — FitBook', loadComponent: () => import('./espaces/admin/activites/activites').then(m => m.ActivitesAdminComponent) },
    ],
  },

  // --- Anciennes adresses (décision D5) -------------------------------------
  { path: 'seances', redirectTo: 'recherche' },
  { path: 'salles', redirectTo: () => inject(Router).createUrlTree(['/recherche'], { queryParams: { type: 'salles' } }) },
  { path: 'coaches', redirectTo: () => inject(Router).createUrlTree(['/recherche'], { queryParams: { type: 'coachs' } }) },
  { path: 'coachs', redirectTo: () => inject(Router).createUrlTree(['/recherche'], { queryParams: { type: 'coachs' } }) },
  { path: 'coaches-salles', redirectTo: () => inject(Router).createUrlTree(['/recherche'], { queryParams: { type: 'salles' } }) },
  { path: 'profil', redirectTo: 'mon-espace/profil' },
  {
    path: 'reservation',
    redirectTo: ({ queryParams }) => {
      const id = Number(queryParams['seance']);
      return inject(Router).createUrlTree(Number.isInteger(id) && id > 0 ? ['/seances', id] : ['/recherche']);
    },
  },

  { path: '**', title: 'Page introuvable — FitBook', loadComponent: () => import('./pages/introuvable/introuvable').then(m => m.IntrouvableComponent) },
];
