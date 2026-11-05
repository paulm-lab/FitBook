import { HttpClient } from '@angular/common/http';
import { Injectable, inject, signal } from '@angular/core';
import { Observable, tap } from 'rxjs';
import { API_URL, params } from '../http';
import { Avis, Favori, NotificationFitBook, Page, Reservation, TypeCible, Utilisateur } from '../models';

/** Espace du compte connecté : réservations, favoris, avis, profil, avatar, notifications. */
@Injectable({ providedIn: 'root' })
export class CompteService {
  private readonly http = inject(HttpClient);

  /** Nombre de notifications non lues, partagé avec le badge de l'en-tête. */
  readonly nonLues = signal(0);

  // --- Réservations -------------------------------------------------------

  reserver(seanceId: number): Observable<Reservation> {
    return this.http.post<Reservation>(`${API_URL}/reservations`, { seanceId });
  }

  reservations(periode: 'a_venir' | 'historique', page = 1, parPage = 20): Observable<Page<Reservation>> {
    return this.http.get<Page<Reservation>>(`${API_URL}/reservations`, { params: params({ periode, page, parPage }) });
  }

  reservation(id: number): Observable<Reservation> {
    return this.http.get<Reservation>(`${API_URL}/reservations/${id}`);
  }

  annulerReservation(id: number): Observable<Reservation> {
    return this.http.post<Reservation>(`${API_URL}/reservations/${id}/annuler`, {});
  }

  // --- Favoris ------------------------------------------------------------

  favoris(): Observable<Favori[]> {
    return this.http.get<Favori[]>(`${API_URL}/favoris`);
  }

  ajouterFavori(type: TypeCible, cibleId: number): Observable<Favori> {
    return this.http.post<Favori>(`${API_URL}/favoris`, { type, cibleId });
  }

  retirerFavori(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/favoris/${id}`);
  }

  // --- Avis ---------------------------------------------------------------

  mesAvis(): Observable<Avis[]> {
    return this.http.get<Avis[]>(`${API_URL}/me/avis`);
  }

  donnerAvis(type: TypeCible, cibleId: number, note: number, commentaire: string): Observable<Avis> {
    return this.http.post<Avis>(`${API_URL}/avis`, { type, cibleId, note, commentaire });
  }

  modifierAvis(id: number, note: number, commentaire: string): Observable<Avis> {
    return this.http.put<Avis>(`${API_URL}/avis/${id}`, { note, commentaire });
  }

  supprimerAvis(id: number): Observable<void> {
    return this.http.delete<void>(`${API_URL}/avis/${id}`);
  }

  // --- Profil -------------------------------------------------------------

  modifierProfil(data: { nom: string; prenom: string; telephone: string }): Observable<Utilisateur> {
    return this.http.put<Utilisateur>(`${API_URL}/me/profile`, data);
  }

  envoyerAvatar(fichier: File): Observable<Utilisateur> {
    const form = new FormData();
    form.append('avatar', fichier);
    return this.http.post<Utilisateur>(`${API_URL}/me/avatar`, form);
  }

  supprimerAvatar(): Observable<Utilisateur> {
    return this.http.delete<Utilisateur>(`${API_URL}/me/avatar`);
  }

  // --- Notifications ------------------------------------------------------

  notifications(): Observable<{ nonLues: number; items: NotificationFitBook[] }> {
    return this.http.get<{ nonLues: number; items: NotificationFitBook[] }>(`${API_URL}/me/notifications`).pipe(
      tap(reponse => this.nonLues.set(reponse.nonLues)),
    );
  }

  marquerNotificationsLues(): Observable<void> {
    return this.http.post<void>(`${API_URL}/me/notifications/lues`, {}).pipe(tap(() => this.nonLues.set(0)));
  }
}
