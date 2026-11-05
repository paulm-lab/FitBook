import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NIVEAUX, SeanceResume } from '../core/models';
import { FavoriButtonComponent } from './favori-button';
import { EtoilesComponent, ImageSecoursDirective } from './ui';

/** Carte d'une séance dans les listes (recherche, fiches salle et coach). */
@Component({
  selector: 'app-seance-card',
  imports: [
    RouterLink,
    DatePipe,
    DecimalPipe,
    FavoriButtonComponent,
    EtoilesComponent,
    ImageSecoursDirective,
  ],
  template: `
    @let s = seance();
    <article class="card card--link">
      <a class="card-media" [routerLink]="['/seances', s.id]" tabindex="-1" aria-hidden="true">
        <img
          [src]="s.image || '/assets/fitbook-media-fallback.svg'"
          alt=""
          loading="lazy"
          decoding="async"
          appSecours
        />
        <span class="badge badge--level">{{ niveau() }}</span>
      </a>
      <div class="card-body">
        <div class="card-top">
          <span class="card-kicker">{{ s.activite?.nom }}</span>
          <app-favori type="seance" [cibleId]="s.id" />
        </div>
        <h3 class="card-title">
          <a [routerLink]="['/seances', s.id]">{{ s.titre }}</a>
        </h3>
        <p class="card-meta">
          <time [attr.datetime]="s.dateDebut">{{ s.dateDebut | date: 'EEEE d MMMM · HH:mm' }}</time>
          – {{ s.dateFin | date: 'HH:mm' }}
        </p>
        <p class="card-meta">
          {{ s.salle?.nom ?? 'Lieu précisé par le coach' }}
          @if (s.coach) {
            · {{ s.coach.prenom }} {{ s.coach.nom }}
          }
          @if (s.distanceKm != null) {
            · {{ s.distanceKm | number: '1.1-1' }} km
          }
        </p>
        @if (s.noteMoyenne !== undefined) {
          <app-etoiles [note]="s.noteMoyenne" [nbAvis]="s.nbAvis ?? null" />
        }
        <div class="card-footer">
          <strong class="price">{{
            +s.prix === 0 ? 'Gratuit' : (s.prix | number: '1.2-2') + ' €'
          }}</strong>
          @if (complet()) {
            <span class="badge badge--full">Complet</span>
          } @else {
            <span class="badge badge--ok"
              >{{ s.placesDisponibles }} place{{ s.placesDisponibles > 1 ? 's' : '' }}</span
            >
          }
        </div>
      </div>
    </article>
  `,
})
export class SeanceCardComponent {
  readonly seance = input.required<SeanceResume>();
  readonly complet = computed(() => this.seance().placesDisponibles <= 0);
  readonly niveau = computed(() => NIVEAUX[this.seance().niveau] ?? this.seance().niveau);
}
