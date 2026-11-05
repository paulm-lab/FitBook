import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL, params } from '../http';
import {
  Activite,
  Avis,
  CoachDetail,
  Disponibilite,
  Evolution,
  Note,
  Page,
  Reservation,
  Salle,
  SeanceResume,
  StatsSalle,
  UtilisateurAdmin,
} from '../models';

/** Corps de création / modification d'une séance (POST, PUT /api/seances). */
export interface SeanceSaisie {
  titre: string;
  description?: string | null;
  activiteId: number | null;
  salleId?: number | null;
  coachId?: number | null;
  debut: string;
  fin: string;
  capacite: number | null;
  prix: number | null;
  niveau: string;
}

export interface SalleSaisie {
  nom: string;
  description?: string | null;
  adresse: string;
  ville: string;
  codePostal: string;
  latitude?: number | null;
  longitude?: number | null;
  telephone?: string | null;
  email?: string | null;
  horaires?: Record<string, string> | null;
  photos?: string[];
  gestionnaireId?: number | null;
}

export interface DisponibiliteSaisie {
  recurrent: boolean;
  jourSemaine?: number | null;
  date?: string | null;
  heureDebut: string;
  heureFin: string;
  salleId?: number | null;
}

export interface ProfilCoachSaisie {
  biographie: string | null;
  specialites: string[];
  experienceAnnees: number | null;
  tarifHoraire: number | null;
  ville: string | null;
  certifications: string | null;
}

/** Fenêtre d'un calendrier ou période d'une liste de séances. */
export interface FiltreSeances {
  periode?: 'a_venir' | 'passees';
  debut?: string;
  fin?: string;
  salleId?: number;
  page?: number;
  parPage?: number;
}

export interface Inscrits {
  seance: SeanceResume;
  inscrits: Reservation[];
  nbConfirmes: number;
}

/**
 * Espaces professionnels : coach, gestionnaire et administrateur.
 * Les droits sont vérifiés par l'API (rôles et Voters) : le front ne fait que masquer.
 */
@Injectable({ providedIn: 'root' })
export class GestionService {
  private readonly http = inject(HttpClient);

  // --- Séances (coach, gestionnaire, admin) ------------------------------

  creerSeance(data: SeanceSaisie): Observable<SeanceResume> {
    return this.http.post<SeanceResume>(`${API_URL}/seances`, data);
  }

  modifierSeance(id: number, data: SeanceSaisie): Observable<SeanceResume> {
    return this.http.put<SeanceResume>(`${API_URL}/seances/${id}`, data);
  }

  supprimerSeance(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/seances/${id}`);
  }

  annulerSeance(id: number): Observable<SeanceResume & { sportifsPrevenus: number }> {
    return this.http.post<SeanceResume & { sportifsPrevenus: number }>(
      `${API_URL}/seances/${id}/annuler`,
      {},
    );
  }

  inscrits(id: number): Observable<Inscrits> {
    return this.http.get<Inscrits>(`${API_URL}/seances/${id}/inscrits`);
  }

  /** Séances d'un espace : liste paginée (periode) ou tableau (fenêtre debut/fin du calendrier). */
  seances(
    espace: 'coach' | 'gestionnaire' | 'admin',
    filtre: FiltreSeances,
  ): Observable<Page<SeanceResume> | SeanceResume[]> {
    return this.http.get<Page<SeanceResume> | SeanceResume[]>(`${API_URL}/${espace}/seances`, {
      params: params(filtre),
    });
  }

  // --- Disponibilités -----------------------------------------------------

  creerDisponibilite(data: DisponibiliteSaisie): Observable<Disponibilite> {
    return this.http.post<Disponibilite>(`${API_URL}/disponibilites`, data);
  }

  modifierDisponibilite(id: number, data: DisponibiliteSaisie): Observable<Disponibilite> {
    return this.http.put<Disponibilite>(`${API_URL}/disponibilites/${id}`, data);
  }

  supprimerDisponibilite(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/disponibilites/${id}`);
  }

  // --- Coach ----------------------------------------------------------------

  profilCoach(): Observable<CoachDetail> {
    return this.http.get<CoachDetail>(`${API_URL}/coach/profil`);
  }

  modifierProfilCoach(data: ProfilCoachSaisie): Observable<CoachDetail> {
    return this.http.put<CoachDetail>(`${API_URL}/coach/profil`, data);
  }

  statsCoach(): Observable<{
    nbSeancesAVenir: number;
    nbInscritsAVenir: number;
    tauxRemplissage: number | null;
    note: Note;
    evolution: Evolution[];
  }> {
    return this.http.get<{
      nbSeancesAVenir: number;
      nbInscritsAVenir: number;
      tauxRemplissage: number | null;
      note: Note;
      evolution: Evolution[];
    }>(`${API_URL}/coach/stats`);
  }

  avisRecus(espace: 'coach' | 'gestionnaire', page = 1): Observable<Page<Avis>> {
    return this.http.get<Page<Avis>>(`${API_URL}/${espace}/avis`, { params: params({ page }) });
  }

  // --- Gestionnaire -------------------------------------------------------

  mesSalles(): Observable<(Salle & { stats: StatsSalle })[]> {
    return this.http.get<(Salle & { stats: StatsSalle })[]>(`${API_URL}/gestionnaire/salles`);
  }

  statsGestionnaire(): Observable<{
    nbSalles: number;
    nbSeances: number;
    nbReservations: number;
    tauxRemplissage: number | null;
    parSalle: (StatsSalle & { salle: { id: number; nom: string } })[];
    evolution: Evolution[];
  }> {
    return this.http.get<{
      nbSalles: number;
      nbSeances: number;
      nbReservations: number;
      tauxRemplissage: number | null;
      parSalle: (StatsSalle & { salle: { id: number; nom: string } })[];
      evolution: Evolution[];
    }>(`${API_URL}/gestionnaire/stats`);
  }

  statsSalle(
    id: number,
  ): Observable<StatsSalle & { salle: { id: number; nom: string }; evolution: Evolution[] }> {
    return this.http.get<
      StatsSalle & { salle: { id: number; nom: string }; evolution: Evolution[] }
    >(`${API_URL}/salles/${id}/stats`);
  }

  reservationsGestionnaire(filtre: {
    periode?: 'a_venir' | 'toutes';
    statut?: string;
    page?: number;
  }): Observable<Page<Reservation>> {
    return this.http.get<Page<Reservation>>(`${API_URL}/gestionnaire/reservations`, {
      params: params(filtre),
    });
  }

  creerSalle(data: SalleSaisie): Observable<Salle> {
    return this.http.post<Salle>(`${API_URL}/salles`, data);
  }

  modifierSalle(id: number, data: SalleSaisie): Observable<Salle> {
    return this.http.put<Salle>(`${API_URL}/salles/${id}`, data);
  }

  supprimerSalle(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/salles/${id}`);
  }

  // --- Administrateur -----------------------------------------------------

  statsAdmin(): Observable<{
    utilisateursParRole: Record<string, number>;
    nbUtilisateurs: number;
    nbSalles: number;
    nbActivites: number;
    nbSeances: number;
    nbSeancesAVenir: number;
    reservationsParStatut: Record<string, number>;
    avisEnAttente: number;
    evolution: Evolution[];
  }> {
    return this.http.get<{
      utilisateursParRole: Record<string, number>;
      nbUtilisateurs: number;
      nbSalles: number;
      nbActivites: number;
      nbSeances: number;
      nbSeancesAVenir: number;
      reservationsParStatut: Record<string, number>;
      avisEnAttente: number;
      evolution: Evolution[];
    }>(`${API_URL}/admin/stats`);
  }

  utilisateurs(filtre: {
    q?: string;
    role?: string;
    page?: number;
  }): Observable<Page<UtilisateurAdmin>> {
    return this.http.get<Page<UtilisateurAdmin>>(`${API_URL}/admin/utilisateurs`, {
      params: params(filtre),
    });
  }

  modifierUtilisateur(
    id: number,
    data: { role?: string; estBloque?: boolean; estVerifie?: boolean },
  ): Observable<UtilisateurAdmin> {
    return this.http.patch<UtilisateurAdmin>(`${API_URL}/admin/utilisateurs/${id}`, data);
  }

  supprimerUtilisateur(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/admin/utilisateurs/${id}`);
  }

  reservationsAdmin(filtre: {
    statut?: string;
    periode?: string;
    page?: number;
  }): Observable<Page<Reservation>> {
    return this.http.get<Page<Reservation>>(`${API_URL}/admin/reservations`, {
      params: params(filtre),
    });
  }

  annulerReservation(id: number): Observable<Reservation> {
    return this.http.post<Reservation>(`${API_URL}/reservations/${id}/annuler`, {});
  }

  avisModeration(filtre: { filtre?: string; page?: number }): Observable<Page<Avis>> {
    return this.http.get<Page<Avis>>(`${API_URL}/admin/avis`, { params: params(filtre) });
  }

  moderer(id: number, action: 'valider' | 'masquer' | 'afficher'): Observable<Avis> {
    return this.http.patch<Avis>(`${API_URL}/admin/avis/${id}`, { action });
  }

  creerActivite(data: {
    nom: string;
    categorie: string;
    description?: string | null;
    image?: string | null;
  }): Observable<Activite> {
    return this.http.post<Activite>(`${API_URL}/activites`, data);
  }

  modifierActivite(
    id: number,
    data: { nom: string; categorie: string; description?: string | null; image?: string | null },
  ): Observable<Activite> {
    return this.http.put<Activite>(`${API_URL}/activites/${id}`, data);
  }

  supprimerActivite(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/activites/${id}`);
  }
}
