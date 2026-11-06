import { HttpContextToken, HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, switchMap, throwError } from 'rxjs';
import { API_URL } from '../http';
import { ToastService } from '../services/toast.service';
import { LangueService } from '../i18n/langue.service';
import { AuthService } from './auth.service';

/** Appels pour lesquels un 401 est une réponse normale (pas une session expirée). */
const SANS_REDIRECTION = [`${API_URL}/me`, `${API_URL}/login`, `${API_URL}/logout`];

/** Requête déjà rejouée après l'effacement d'un cookie invalide : pas de seconde tentative. */
const REJOUEE = new HttpContextToken<boolean>(() => false);

/**
 * Intercepteur de l'API :
 * - envoie le cookie de session (withCredentials) et, s'il est connu, le jeton JWT en en-tête ;
 * - sur un 401 (jeton expiré), oublie la session et renvoie vers /connexion, puis vers la page
 *   demandée après la reconnexion (US-S01) ;
 * - sur un 401 d'un visiteur sur une lecture publique (cookie JWT invalide ou expiré), fait
 *   effacer le cookie par /api/logout puis rejoue la requête une seule fois ;
 * - sur un 429, affiche le message de limitation.
 */
export const apiInterceptor: HttpInterceptorFn = (request, next) => {
  if (!request.url.startsWith(API_URL)) {
    return next(request);
  }

  const auth = inject(AuthService);
  const router = inject(Router);
  const toasts = inject(ToastService);
  const langue = inject(LangueService);

  const token = auth.token;
  const authenticated = request.clone({
    withCredentials: true,
    // Langue des messages de l'API (erreurs, validations).
    setHeaders: {
      'Accept-Language': langue.langue(),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  return next(authenticated).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse) {
        if (error.status === 401 && !SANS_REDIRECTION.includes(request.url) && auth.estConnecte()) {
          auth.oublierSession();
          toasts.info(langue.t('Votre session a expiré. Reconnectez-vous pour continuer.'));
          void router.navigate(['/connexion'], { queryParams: { returnUrl: router.url } });
        } else if (
          error.status === 401 &&
          request.method === 'GET' &&
          !SANS_REDIRECTION.includes(request.url) &&
          !request.context.get(REJOUEE)
        ) {
          const rejouee = authenticated.clone({ context: request.context.set(REJOUEE, true) });
          return auth.effacerCookie().pipe(switchMap(() => next(rejouee)));
        } else if (error.status === 429) {
          toasts.erreur(
            typeof error.error?.message === 'string'
              ? error.error.message
              : langue.t('Trop de tentatives. Patientez un instant.'),
          );
        }
      }
      return throwError(() => error);
    }),
  );
};
