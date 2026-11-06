import { Injectable, effect, inject } from '@angular/core';
import { Title } from '@angular/platform-browser';
import { RouterStateSnapshot, TitleStrategy } from '@angular/router';
import { LangueService } from './langue.service';

/** Titre de l'onglet traduit (titres des routes en français), mis à jour au changement de langue. */
@Injectable({ providedIn: 'root' })
export class TitreTraduitStrategy extends TitleStrategy {
  private readonly title = inject(Title);
  private readonly langue = inject(LangueService);
  private titreCourant = '';

  constructor() {
    super();
    effect(() => {
      const traduit = this.langue.t(this.titreCourant);
      if (traduit) this.title.setTitle(traduit);
    });
  }

  override updateTitle(snapshot: RouterStateSnapshot): void {
    const titre = this.buildTitle(snapshot);
    if (titre === undefined) return;
    this.titreCourant = titre;
    this.title.setTitle(this.langue.t(titre));
  }
}
