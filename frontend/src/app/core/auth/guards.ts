import { inject } from '@angular/core';
import { toObservable } from '@angular/core/rxjs-interop';
import { CanActivateFn, Router } from '@angular/router';
import { filter, map, take } from 'rxjs';
import { Role } from '../models';
import { AuthService } from './auth.service';

/**
 * Attend la fin de la vérification de session (/api/me au démarrage) avant de décider :
 * un rechargement de page sur un espace protégé ne renvoie pas à tort vers /connexion.
 */
function decider(decision: (auth: AuthService) => boolean | ReturnType<Router['createUrlTree']>) {
  const auth = inject(AuthService);
  if (auth.pret()) {
    return decision(auth);
  }
  return toObservable(auth.pret).pipe(
    filter(Boolean),
    take(1),
    map(() => decision(auth)),
  );
}

/** Page réservée aux comptes connectés : sinon /connexion?returnUrl=… */
export const authGuard: CanActivateFn = (_route, state) => {
  const router = inject(Router);
  return decider(
    (auth) =>
      auth.estConnecte() ||
      router.createUrlTree(['/connexion'], { queryParams: { returnUrl: state.url } }),
  );
};

/**
 * Espace réservé à certains rôles (l'administrateur passe partout). Un compte connecté
 * sans le bon rôle est renvoyé vers son propre espace. Le contrôle réel reste côté API.
 */
export function roleGuard(...roles: Role[]): CanActivateFn {
  return (_route, state) => {
    const router = inject(Router);
    return decider((auth) => {
      if (!auth.estConnecte()) {
        return router.createUrlTree(['/connexion'], { queryParams: { returnUrl: state.url } });
      }
      return auth.aLeRole(...roles) || router.createUrlTree([auth.espace()]);
    });
  };
}

/** Pages connexion / inscription : inutiles une fois connecté. */
export const invitesGuard: CanActivateFn = () => {
  const router = inject(Router);
  return decider((auth) => !auth.estConnecte() || router.createUrlTree([auth.espace()]));
};
