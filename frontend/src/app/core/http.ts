import { HttpErrorResponse, HttpParams } from '@angular/common/http';
import { traduire } from './i18n/langue.service';
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

/**
 * L'API ne répond pas : réseau coupé (statut 0), passerelle en erreur (502, 504), ou 500/503
 * sans aucun corps — c'est la réponse du proxy de `ng serve` quand le backend est arrêté.
 * Une erreur renvoyée par Symfony a toujours un corps JSON.
 */
export function serveurInjoignable(error: unknown): boolean {
  if (!(error instanceof HttpErrorResponse)) return false;
  const sansCorps = error.error === null || error.error === undefined || error.error === '';
  return [0, 502, 504].includes(error.status) || ([500, 503].includes(error.status) && sansCorps);
}

export const MESSAGE_INJOIGNABLE =
  'Le serveur FitBook est injoignable. Vérifiez votre connexion ; en développement, vérifiez que l’API est démarrée (symfony serve).';

/**
 * Message lisible d'une erreur HTTP, pour un toast ou un bandeau. Les messages de l'API sont
 * déjà dans la langue demandée (Accept-Language) ; les messages du front sont traduits ici.
 */
export function messageErreur(
  error: unknown,
  defaut = 'Une erreur est survenue. Réessayez.',
): string {
  if (!(error instanceof HttpErrorResponse)) return traduire(defaut);
  if (serveurInjoignable(error)) return traduire(MESSAGE_INJOIGNABLE);
  const body = error.error as ApiErreur | null;
  if (body?.errors && Object.keys(body.errors).length > 0) return Object.values(body.errors)[0];
  return typeof body?.message === 'string' && body.message ? body.message : traduire(defaut);
}

/** Erreurs de validation 422 par champ ({} si la réponse n'en contient pas). */
export function erreursChamps(error: unknown): Record<string, string> {
  if (error instanceof HttpErrorResponse && error.status === 422) {
    return (error.error as ApiErreur | null)?.errors ?? {};
  }
  return {};
}
