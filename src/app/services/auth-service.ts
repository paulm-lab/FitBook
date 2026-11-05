import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable, catchError, map, of, tap, timeout } from 'rxjs';
import { API_URL } from '../api';
import { UtilisateurConnecte } from '../models/utilisateur';

export interface InscriptionResponse { message: string; email: string; verificationEmailQueued: boolean; }

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly utilisateurSubject = new BehaviorSubject<UtilisateurConnecte | null>(null);
  readonly utilisateur$ = this.utilisateurSubject.asObservable();
  get utilisateur(): UtilisateurConnecte | null { return this.utilisateurSubject.value; }
  mettreAJourUtilisateur(user: UtilisateurConnecte): void { this.utilisateurSubject.next(user); }
  constructor(private readonly http: HttpClient) {}

  verifierSession(): Observable<UtilisateurConnecte> {
    return this.http.get<UtilisateurConnecte>(`${API_URL}/me`, { withCredentials: true }).pipe(
      tap(user => this.utilisateurSubject.next(user)),
      catchError(error => { this.utilisateurSubject.next(null); throw error; })
    );
  }
  connexion(email: string, password: string): Observable<UtilisateurConnecte> {
    return this.http.post<UtilisateurConnecte>(`${API_URL}/login`, { email, password }, { withCredentials: true }).pipe(tap(user => this.utilisateurSubject.next(user)));
  }
  inscription(data: { nom: string; prenom: string; email: string; telephone: string; password: string }): Observable<InscriptionResponse> {
    return this.http.post<InscriptionResponse>(`${API_URL}/inscription`, data, { withCredentials: true }).pipe(timeout({ first: 20000 }));
  }
  renvoyerVerification(email: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${API_URL}/auth/resend-verification`, { email }, { withCredentials: true }).pipe(timeout({ first: 20000 }));
  }
  deconnexion(): Observable<void> {
    return this.http.post<void>(`${API_URL}/logout`, {}, { withCredentials: true }).pipe(
      map(() => undefined),
      catchError(() => of(undefined)),
      tap(() => this.utilisateurSubject.next(null))
    );
  }
}
