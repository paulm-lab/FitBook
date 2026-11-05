import { DecimalPipe } from '@angular/common';
import { Component, computed, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Coach } from '../core/models';
import { FavoriButtonComponent } from './favori-button';
import { EtoilesComponent, ImageSecoursDirective } from './ui';

/** Carte d'un coach : photo (ou initiales), spécialités, tarif horaire, note. */
@Component({
  selector: 'app-coach-card',
  imports: [
    RouterLink,
    DecimalPipe,
    FavoriButtonComponent,
    EtoilesComponent,
    ImageSecoursDirective,
  ],
  template: `
    @let c = coach();
    <article class="card card--link coach-card">
      <div class="card-body">
        <div class="card-top">
          <span class="card-kicker">Coach{{ c.ville ? ' · ' + c.ville : '' }}</span>
          <app-favori type="coach" [cibleId]="c.id" />
        </div>
        <div class="row">
          @if (c.photo) {
            <img class="avatar avatar--lg" [src]="c.photo" alt="" loading="lazy" appSecours />
          } @else {
            <span class="avatar avatar--lg avatar--initiales" aria-hidden="true">{{
              initiales()
            }}</span>
          }
          <div>
            <h3 class="card-title">
              <a [routerLink]="['/coachs', c.id]">{{ c.prenom }} {{ c.nom }}</a>
            </h3>
            @if (c.experienceAnnees != null) {
              <p class="card-meta">{{ c.experienceAnnees }} ans d’expérience</p>
            }
          </div>
        </div>
        @if (c.specialites.length) {
          <div class="chips">
            @for (s of c.specialites.slice(0, 4); track s) {
              <span class="chip">{{ s }}</span>
            }
          </div>
        }
        <div class="card-footer">
          <app-etoiles [note]="c.noteMoyenne" [nbAvis]="c.nbAvis ?? null" />
          @if (c.tarifHoraire) {
            <strong class="price">{{ c.tarifHoraire | number: '1.0-2' }} €/h</strong>
          }
        </div>
      </div>
    </article>
  `,
})
export class CoachCardComponent {
  readonly coach = input.required<Coach>();
  readonly initiales = computed(() =>
    `${this.coach().prenom?.[0] ?? ''}${this.coach().nom?.[0] ?? ''}`.toUpperCase(),
  );
}
