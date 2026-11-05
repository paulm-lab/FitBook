import { DecimalPipe } from '@angular/common';
import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Salle } from '../core/models';
import { FavoriButtonComponent } from './favori-button';
import { EtoilesComponent, ImageSecoursDirective } from './ui';

/** Carte d'une salle dans les résultats de recherche et les favoris. */
@Component({
  selector: 'app-salle-card',
  imports: [
    RouterLink,
    DecimalPipe,
    FavoriButtonComponent,
    EtoilesComponent,
    ImageSecoursDirective,
  ],
  template: `
    @let s = salle();
    <article class="card card--link">
      <a class="card-media" [routerLink]="['/salles', s.id]" tabindex="-1" aria-hidden="true">
        <img
          [src]="s.image || '/assets/fitbook-media-fallback.svg'"
          alt=""
          loading="lazy"
          decoding="async"
          appSecours
        />
      </a>
      <div class="card-body">
        <div class="card-top">
          <span class="card-kicker">Salle · {{ s.ville }}</span>
          <app-favori type="salle" [cibleId]="s.id" />
        </div>
        <h3 class="card-title">
          <a [routerLink]="['/salles', s.id]">{{ s.nom }}</a>
        </h3>
        <p class="card-meta">{{ s.adresse }}</p>
        @if (s.distanceKm != null) {
          <p class="card-meta">À {{ s.distanceKm | number: '1.1-1' }} km</p>
        }
        <div class="card-footer">
          <app-etoiles [note]="s.noteMoyenne" [nbAvis]="s.nbAvis ?? null" />
          <a class="btn btn--sm btn--ghost" [routerLink]="['/salles', s.id]">Voir la salle</a>
        </div>
      </div>
    </article>
  `,
})
export class SalleCardComponent {
  readonly salle = input.required<Salle>();
}
