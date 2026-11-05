import { HttpErrorResponse, HttpParams } from '@angular/common/http';
import { ApiErreur } from './models';

// Relatif : passe par le proxy du serveur Angular (proxy.conf.json → Symfony), ce qui garde
// le cookie HttpOnly du jeton JWT sur la même origine que le front.
export const API_URL = '/api';

/** Paramètres de requête sans les valeurs vides (null, undefined, chaîne vide). */
export function params(values: object = {}): HttpParams {
  let result = new HttpParams();
  for (const [key, value] of Object.entries(values)) {
    if (value !== null && value !== undefined && value !== '') {
      result = result.set(key, String(value));
    }
  }
  return result;
}

/** Message lisible d'une erreur HTTP, pour un toast ou un bandeau. */
export function messageErreur(error: unknown, defaut = 'Une erreur est survenue. Réessayez.'): string {
  if (!(error instanceof HttpErrorResponse)) return defaut;
  if (error.status === 0) return 'Le serveur FitBook est injoignable. Vérifiez votre connexion.';
  const body = error.error as ApiErreur | null;
  if (body?.errors && Object.keys(body.errors).length > 0) return Object.values(body.errors)[0];
  return typeof body?.message === 'string' && body.message ? body.message : defaut;
}

/** Erreurs de validation 422 par champ ({} si la réponse n'en contient pas). */
export function erreursChamps(error: unknown): Record<string, string> {
  if (error instanceof HttpErrorResponse && error.status === 422) {
    return (error.error as ApiErreur | null)?.errors ?? {};
  }
  return {};
}
