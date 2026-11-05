import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';
import { API_URL } from '../api';
import { UtilisateurConnecte } from '../models/utilisateur';
import { AuthService } from './auth-service';

export type ProfilModifiable = Pick<UtilisateurConnecte, 'nom' | 'prenom' | 'telephone'>;

@Injectable({ providedIn: 'root' })
export class ProfilService {
  constructor(private readonly http: HttpClient, private readonly auth: AuthService) {}

  getProfil(): Observable<UtilisateurConnecte> {
    return this.http.get<UtilisateurConnecte>(`${API_URL}/me/profile`, { withCredentials: true });
  }

  modifierProfil(profil: ProfilModifiable): Observable<UtilisateurConnecte> {
    return this.http.put<UtilisateurConnecte>(`${API_URL}/me/profile`, profil, { withCredentials: true }).pipe(
      tap(user => this.auth.mettreAJourUtilisateur(user))
    );
  }
}
