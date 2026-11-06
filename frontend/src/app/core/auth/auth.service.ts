import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, catchError, finalize, map, of, shareReplay, tap, timeout } from 'rxjs';
import { API_URL } from '../http';
import { Role, Utilisateur } from '../models';

// sent : accepté par le serveur SMTP ; queued : en file Messenger, pas encore parti ; failed : ni l'un ni l'autre.
export type StatutEmailVerification = 'sent' | 'queued' | 'failed';

/** Code renvoyé par l'API (403 à la connexion) quand l'adresse n'est pas encore confirmée. */
export const CODE_EMAIL_NON_VERIFIE = 'email_non_verifie';

export interface InscriptionSaisie {
  prenom: string;
  nom: string;
  email: string;
  telephone: string;
  password: string;
  role: Exclude<Role, 'admin'>;
}

export interface InscriptionResponse {
  message: string;
  email: string;
  role?: Role;
  verificationEmailQueued?: boolean;
  verificationEmailStatus?: StatutEmailVerification;
}

/** Espace d'accueil de chaque rôle après la connexion. */
export const ESPACE_PAR_ROLE: Record<Role, string> = {
  sportif: '/mon-espace',
  coach: '/coach',
  gestionnaire: '/gestionnaire',
  admin: '/admin',
};

/**
 * Session de l'utilisateur, exposée en signals.
 *
 * Le jeton JWT est posé par l'API dans un cookie HttpOnly (inaccessible au JavaScript :
 * protection XSS). Il est aussi gardé en mémoire, jamais dans localStorage, pour être joint
 * à chaque appel dans l'en-tête Authorization (voir apiInterceptor). Après un rechargement,
 * seul le cookie subsiste : /api/me restaure la session.
 */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly utilisateurCourant = signal<Utilisateur | null>(null);
  private readonly sessionVerifiee = signal(false);
  private jeton: string | null = null;
  /** Effacement du cookie en cours : partagé par les requêtes refusées en même temps. */
  private effacement: Observable<void> | null = null;

  /** Utilisateur connecté, ou null. */
  readonly utilisateur = this.utilisateurCourant.asReadonly();
  /** Vrai quand /api/me a répondu (les guards attendent ce moment). */
  readonly pret = this.sessionVerifiee.asReadonly();
  readonly estConnecte = computed(() => this.utilisateurCourant() !== null);
  readonly role = computed<Role | null>(() => this.utilisateurCourant()?.role ?? null);
  readonly espace = computed(() => ESPACE_PAR_ROLE[this.role() ?? 'sportif']);

  get token(): string | null {
    return this.jeton;
  }

  aLeRole(...roles: Role[]): boolean {
    const role = this.role();
    // L'administrateur hérite de tous les rôles (role_hierarchy côté Symfony).
    return role !== null && (role === 'admin' || roles.includes(role));
  }

  /** Restaure la session depuis le cookie (au démarrage) ; ne lève jamais d'erreur. */
  verifierSession(): Observable<Utilisateur | null> {
    return this.http.get<Utilisateur>(`${API_URL}/me`).pipe(
      // API muette : au bout de 5 s, l'application démarre en visiteur au lieu de rester blanche.
      timeout({ first: 5000 }),
      catchError(() => of(null)),
      tap((user) => {
        this.utilisateurCourant.set(user);
        this.sessionVerifiee.set(true);
      }),
    );
  }

  connexion(email: string, password: string): Observable<Utilisateur> {
    return this.http
      .post<Utilisateur & { token?: string }>(`${API_URL}/login`, { email, password })
      .pipe(
        map(({ token, ...user }) => {
          this.jeton = token ?? null;
          this.utilisateurCourant.set(user);
          this.sessionVerifiee.set(true);
          return user;
        }),
      );
  }

  deconnexion(): Observable<void> {
    return this.http.post<void>(`${API_URL}/logout`, {}).pipe(
      catchError(() => of(undefined)),
      map(() => undefined),
      tap(() => this.oublierSession()),
    );
  }

  /** Session expirée ou révoquée (401) : on oublie l'utilisateur sans appeler l'API. */
  oublierSession(): void {
    this.jeton = null;
    this.utilisateurCourant.set(null);
  }

  /**
   * Cookie JWT invalide ou expiré chez un visiteur (401 sur une page publique) : /api/logout
   * demande au navigateur de l'oublier. Un seul appel à la fois, erreurs ignorées.
   */
  effacerCookie(): Observable<void> {
    this.effacement ??= this.deconnexion().pipe(
      finalize(() => (this.effacement = null)),
      shareReplay(1),
    );
    return this.effacement;
  }

  mettreAJour(user: Utilisateur): void {
    this.utilisateurCourant.set(user);
  }

  inscription(data: InscriptionSaisie): Observable<InscriptionResponse> {
    return this.http
      .post<InscriptionResponse>(`${API_URL}/inscription`, data)
      .pipe(timeout({ first: 20000 }));
  }

  /** Nouveau lien de confirmation (réponse neutre : ne dit pas si le compte existe). */
  renvoyerVerification(email: string): Observable<{ message: string }> {
    return this.http
      .post<{ message: string }>(`${API_URL}/auth/resend-verification`, { email })
      .pipe(timeout({ first: 20000 }));
  }

  motDePasseOublie(email: string): Observable<{ message: string }> {
    return this.http
      .post<{ message: string }>(`${API_URL}/auth/forgot-password`, { email })
      .pipe(timeout({ first: 20000 }));
  }

  reinitialiserMotDePasse(token: string, password: string): Observable<{ message: string }> {
    return this.http.post<{ message: string }>(`${API_URL}/auth/reset-password`, {
      token,
      password,
    });
  }
}
