import { inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';

export type Espace = 'coach' | 'gestionnaire' | 'admin';

/**
 * Espace courant (coach, gestionnaire ou admin), lu dans la donnée `espace` de la route
 * parente (app.routes.ts). À appeler dans un contexte d'injection.
 */
export function espaceCourant(): Espace {
  const route = inject(ActivatedRoute);
  for (const r of [...route.pathFromRoot].reverse()) {
    const espace = r.snapshot.data['espace'];
    if (espace === 'coach' || espace === 'gestionnaire' || espace === 'admin') return espace;
  }
  return 'coach';
}

/** Date locale AAAA-MM-JJ d'un objet Date. */
export function dateLocale(date: Date): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}`;
}

/** Heure locale HH:MM d'un objet Date. */
export function heureLocale(date: Date): string {
  return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`;
}
