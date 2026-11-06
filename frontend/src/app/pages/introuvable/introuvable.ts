import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { EtatVideComponent } from '../../shared/ui';

import { I18N } from '../../core/i18n/pipes';

/** Page 404 : adresse inconnue du front. */
@Component({
  selector: 'app-introuvable',
  imports: [...I18N, RouterLink, EtatVideComponent],
  template: `
    <main class="container page" id="contenu">
      <app-etat-vide
        titre="Page introuvable"
        icone="404"
        message="Cette adresse n’existe pas ou a changé. Retrouvez votre prochaine séance depuis l’accueil ou la recherche."
      >
        <div class="row" style="justify-content: center">
          <a class="btn" routerLink="/">{{ 'Retour à l’accueil' | t }}</a>
          <a class="btn btn--ghost" routerLink="/recherche">{{ 'Rechercher une séance' | t }}</a>
        </div>
      </app-etat-vide>
    </main>
  `,
})
export class IntrouvableComponent {}
