import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';
import { API_URL } from '../http';
import { ToastService } from '../services/toast.service';
import { AuthService } from './auth.service';

/** Appels pour lesquels un 401 est une réponse normale (pas une session expirée). */
const SANS_REDIRECTION = [`${API_URL}/me`, `${API_URL}/login`, `${API_URL}/logout`];

/**
 * Intercepteur de l'API :
 * - envoie le cookie de session (withCredentials) et, s'il est connu, le jeton JWT en en-tête ;
 * - sur un 401 (jeton expiré), oublie la session et renvoie vers /connexion, puis vers la page
 *   demandée après la reconnexion (US-S01) ;
 * - sur un 429, affiche le message de limitation.
 */
export const apiInterceptor: HttpInterceptorFn = (request, next) => {
  if (!request.url.startsWith(API_URL)) {
    return next(request);
  }

  const auth = inject(AuthService);
  const router = inject(Router);
  const toasts = inject(ToastService);

  const token = auth.token;
  const authenticated = request.clone({
    withCredentials: true,
    setHeaders: token ? { Authorization: `Bearer ${token}` } : {},
  });

  return next(authenticated).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401 && !SANS_REDIRECTION.includes(request.url) && auth.estConnecte()) {
          auth.oublierSession();
          toasts.info('Votre session a expiré. Reconnectez-vous pour continuer.');
          void router.navigate(['/connexion'], { queryParams: { returnUrl: router.url } });
        } else if (error.status === 429) {
          toasts.erreur(typeof error.error?.message === 'string' ? error.error.message : 'Trop de tentatives. Patientez un instant.');
        }
      }
      return throwError(() => error);
    }),
  );
};
