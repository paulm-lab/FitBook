import { formatDate, formatNumber } from '@angular/common';
import { Pipe, PipeTransform, inject } from '@angular/core';
import { LangueService, ParametresTexte } from './langue.service';

/**
 * Traduction dans un gabarit : {{ 'Rechercher' | t }}, {{ 'Bonjour {prenom}' | t: { prenom } }}.
 * Impur : le résultat change avec la langue, sans changement de l'entrée.
 */
@Pipe({ name: 't', pure: false })
export class TraductionPipe implements PipeTransform {
  private readonly langue = inject(LangueService);

  transform(texte: string | null | undefined, parametres?: ParametresTexte): string {
    return texte ? this.langue.t(texte, parametres) : '';
  }
}

/** Remplace le pipe date : même format, dans la langue courante ({{ d | dateL: 'd MMM y' }}). */
@Pipe({ name: 'dateL', pure: false })
export class DateLanguePipe implements PipeTransform {
  private readonly langue = inject(LangueService);

  transform(valeur: string | number | Date | null | undefined, format = 'mediumDate'): string {
    if (valeur === null || valeur === undefined || valeur === '') return '';
    return formatDate(valeur, format, this.langue.locale());
  }
}

/** Remplace le pipe number : séparateurs de la langue courante ({{ prix | nombreL: '1.2-2' }}). */
@Pipe({ name: 'nombreL', pure: false })
export class NombreLanguePipe implements PipeTransform {
  private readonly langue = inject(LangueService);

  transform(valeur: number | string | null | undefined, format?: string): string {
    if (valeur === null || valeur === undefined || valeur === '') return '';
    return formatNumber(Number(valeur), this.langue.locale(), format);
  }
}

/** Pipes de langue à importer ensemble dans un composant. */
export const I18N = [TraductionPipe, DateLanguePipe, NombreLanguePipe] as const;
