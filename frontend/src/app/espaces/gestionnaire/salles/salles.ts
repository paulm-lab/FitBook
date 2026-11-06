import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { Salle, StatsSalle } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import {
  EtatVideComponent,
  EtoilesComponent,
  ImageSecoursDirective,
  LoaderComponent,
} from '../../../shared/ui';

/** Mes salles (US-G01) : cartes avec leurs statistiques et leurs actions. */
@Component({
  selector: 'app-mes-salles',
  imports: [
    RouterLink,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    ImageSecoursDirective,
    ...I18N,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Mes salles' | t }}</span>
        <h1>{{ 'Salles gérées' | t }}</h1>
        <p>{{ 'Informations, horaires, photos et cours de chacune de vos salles.' | t }}</p>
      </div>
      <a class="btn" routerLink="/gestionnaire/salles/nouvelle">{{ '+ Nouvelle salle' | t }}</a>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (salles().length === 0) {
      <app-etat-vide
        [titre]="'Aucune salle' | t"
        [message]="'Créez votre première salle pour y programmer des cours.' | t"
        icone="🏋"
      >
        <a class="btn btn--sm" routerLink="/gestionnaire/salles/nouvelle">{{
          'Créer une salle' | t
        }}</a>
      </app-etat-vide>
    } @else {
      <div class="grid">
        @for (s of salles(); track s.id) {
          <article class="card">
            <div class="card-media">
              <img
                [src]="s.image || '/assets/fitbook-media-fallback.svg'"
                alt=""
                loading="lazy"
                appSecours
              />
            </div>
            <div class="card-body">
              <span class="card-kicker">{{ s.ville }}</span>
              <h2 class="card-title">{{ s.nom }}</h2>
              <p class="card-meta">{{ s.adresse }}</p>
              <app-etoiles [note]="s.stats.noteMoyenne" [nbAvis]="s.stats.nbAvis" />
              <p class="card-meta">
                {{
                  (s.stats.nbSeances === 1 ? '{n} séance' : '{n} séances')
                    | t: { n: s.stats.nbSeances }
                }}
                ·
                {{
                  (s.stats.nbReservations === 1 ? '{n} réservation' : '{n} réservations')
                    | t: { n: s.stats.nbReservations }
                }}
                ·
                {{
                  s.stats.tauxRemplissage !== null
                    ? ('{taux} % rempli'
                      | t: { taux: (s.stats.tauxRemplissage | nombreL: '1.0-1') })
                    : ('pas encore de séance' | t)
                }}
              </p>
              <div class="row" style="margin-top:auto;padding-top:10px;gap:6px">
                <a class="btn btn--sm" [routerLink]="['/gestionnaire/salles', s.id]">{{
                  'Modifier' | t
                }}</a>
                <a
                  class="btn btn--sm btn--ghost"
                  routerLink="/gestionnaire/seances"
                  [queryParams]="{ salleId: s.id }"
                  >{{ 'Séances' | t }}</a
                >
                <a class="btn btn--sm btn--ghost" [routerLink]="['/salles', s.id]">{{
                  'Fiche publique' | t
                }}</a>
              </div>
            </div>
          </article>
        }
      </div>
    }
  `,
})
export class MesSallesComponent {
  private readonly gestion = inject(GestionService);
  private readonly langue = inject(LangueService);
  readonly salles = signal<(Salle & { stats: StatsSalle })[]>([]);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    this.gestion.mesSalles().subscribe({
      next: (s) => {
        this.salles.set(s);
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(messageErreur(error, this.langue.t('Impossible de charger vos salles.')));
        this.chargement.set(false);
      },
    });
  }
}
