import { DOCUMENT } from '@angular/common';
import { Injectable, effect, inject, signal } from '@angular/core';

export type Theme = 'clair' | 'sombre';

const CLE = 'fitbook-theme';

/**
 * Mode sombre optionnel (cahier des charges § 8) : préférence du système par défaut,
 * choix de l'utilisateur mémorisé dans le navigateur. Le thème est appliqué par l'attribut
 * data-theme de <html>, qui redéfinit les jetons de couleur de styles.css.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  readonly theme = signal<Theme>(this.initial());

  constructor() {
    effect(() => {
      const theme = this.theme();
      this.document.documentElement.dataset['theme'] = theme === 'sombre' ? 'dark' : 'light';
      try {
        localStorage.setItem(CLE, theme);
      } catch {
        // Stockage indisponible (navigation privée) : le choix vaut pour la session seulement.
      }
    });
  }

  basculer(): void {
    this.theme.update(theme => (theme === 'sombre' ? 'clair' : 'sombre'));
  }

  private initial(): Theme {
    try {
      const enregistre = localStorage.getItem(CLE);
      if (enregistre === 'clair' || enregistre === 'sombre') return enregistre;
    } catch {
      // Ignoré : on suit la préférence du système.
    }
    return this.document.defaultView?.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'sombre' : 'clair';
  }
}
