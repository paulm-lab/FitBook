import { DOCUMENT } from '@angular/common';
import { Injectable, computed, effect, inject, signal } from '@angular/core';
import { EN } from './en/index';

export type Langue = 'fr' | 'en';

/** Paramètres insérés dans un texte : « Bonjour {prenom} » + { prenom: 'Camille' }. */
export type ParametresTexte = Record<string, string | number | null | undefined>;

const CLE = 'fitbook-langue';

/** Traduction du service actif, pour traduire hors d'un contexte d'injection (fonctions utilitaires). */
let traductionActive: ((texte: string, parametres?: ParametresTexte) => string) | null = null;

/** Locale Angular (dates, nombres) de chaque langue du site. */
export const LOCALE_PAR_LANGUE: Record<Langue, string> = { fr: 'fr', en: 'en-GB' };

/**
 * Langue du site (français par défaut, anglais au choix), mémorisée dans le navigateur.
 *
 * Le texte français sert de clé : t('Rechercher') renvoie « Rechercher » en français et sa
 * traduction des dictionnaires anglais (core/i18n/en) en anglais. Un texte sans traduction
 * reste en français plutôt que d'afficher une clé technique.
 */
@Injectable({ providedIn: 'root' })
export class LangueService {
  private readonly document = inject(DOCUMENT);
  readonly langue = signal<Langue>(this.initiale());
  /** Locale Angular à passer à formatDate / formatNumber. */
  readonly locale = computed(() => LOCALE_PAR_LANGUE[this.langue()]);

  constructor() {
    traductionActive = (texte, parametres) => this.t(texte, parametres);
    effect(() => {
      const langue = this.langue();
      this.document.documentElement.lang = langue;
      try {
        localStorage.setItem(CLE, langue);
      } catch {
        // Stockage indisponible (navigation privée) : le choix vaut pour la session seulement.
      }
    });
  }

  choisir(langue: Langue): void {
    this.langue.set(langue);
  }

  basculer(): void {
    this.langue.update((langue) => (langue === 'fr' ? 'en' : 'fr'));
  }

  /** Texte dans la langue courante ; lit le signal, donc réévalué au changement de langue. */
  t(texte: string, parametres?: ParametresTexte): string {
    const traduit = this.langue() === 'en' ? (EN[texte] ?? texte) : texte;
    return parametres ? remplir(traduit, parametres) : traduit;
  }

  private initiale(): Langue {
    try {
      const enregistree = localStorage.getItem(CLE);
      if (enregistree === 'fr' || enregistree === 'en') return enregistree;
    } catch {
      // Ignoré : français par défaut.
    }
    return 'fr';
  }
}

/**
 * Traduction hors composant (ex. messageErreur de core/http) : passe par le service actif,
 * ou renvoie le texte français si aucun service n'a encore été créé.
 */
export function traduire(texte: string, parametres?: ParametresTexte): string {
  if (traductionActive) return traductionActive(texte, parametres);
  return parametres ? remplir(texte, parametres) : texte;
}

function remplir(texte: string, parametres: ParametresTexte): string {
  return texte.replace(/\{(\w+)\}/g, (tout, nom: string) =>
    nom in parametres ? String(parametres[nom] ?? '') : tout,
  );
}
