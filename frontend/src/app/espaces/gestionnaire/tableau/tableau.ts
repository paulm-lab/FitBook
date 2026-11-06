import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { Evolution, StatsSalle } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { GraphiqueEvolutionComponent } from '../../../shared/graphique';
import { EtatVideComponent, EtoilesComponent, LoaderComponent } from '../../../shared/ui';

interface StatsGestionnaire {
  nbSalles: number;
  nbSeances: number;
  nbReservations: number;
  tauxRemplissage: number | null;
  parSalle: (StatsSalle & { salle: { id: number; nom: string } })[];
  evolution: Evolution[];
}

/** Tableau de bord du gestionnaire (US-G05) : chiffres de sp_stats_salle et évolution. */
@Component({
  selector: 'app-tableau-gestionnaire',
  imports: [
    RouterLink,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    GraphiqueEvolutionComponent,
    ...I18N,
  ],
  styles: `
    .bar {
      width: 100%;
      min-width: 80px;
      height: 8px;
      overflow: hidden;
      border-radius: 4px;
      background: var(--surface-alt);
    }
    .bar span {
      display: block;
      height: 100%;
      border-radius: 4px;
      background: var(--primary);
    }
  `,
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Espace gestionnaire' | t }}</span>
        <h1>{{ 'Tableau de bord' | t }}</h1>
        <p>{{ 'Réservations, remplissage et satisfaction de vos salles.' | t }}</p>
      </div>
      <div class="row">
        <a class="btn btn--ghost" routerLink="/gestionnaire/seances/nouvelle">{{
          '+ Nouvelle séance' | t
        }}</a>
        <a class="btn" routerLink="/gestionnaire/salles/nouvelle">{{ '+ Nouvelle salle' | t }}</a>
      </div>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (stats(); as s) {
      @if (s.nbSalles === 0) {
        <app-etat-vide
          [titre]="'Créez votre première salle' | t"
          [message]="
            'Ajoutez votre salle pour y programmer des cours et suivre vos réservations.' | t
          "
          icone="🏋"
        >
          <a class="btn btn--sm" routerLink="/gestionnaire/salles/nouvelle">{{
            'Créer une salle' | t
          }}</a>
        </app-etat-vide>
      } @else {
        <div class="stats">
          <div class="stat">
            <span class="stat-value">{{ s.nbSalles }}</span
            ><span class="stat-label">{{ (s.nbSalles > 1 ? 'salles' : 'salle') | t }}</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{ s.nbSeances }}</span
            ><span class="stat-label">{{ 'séances actives' | t }}</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{ s.nbReservations }}</span
            ><span class="stat-label">{{ 'réservations' | t }}</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{
              s.tauxRemplissage !== null ? (s.tauxRemplissage | nombreL: '1.0-1') + ' %' : '—'
            }}</span
            ><span class="stat-label">{{ 'taux de remplissage' | t }}</span>
          </div>
        </div>

        <section class="panel" style="margin-bottom:24px">
          <h2>{{ 'Réservations par semaine' | t }}</h2>
          <app-graphique-evolution [donnees]="s.evolution" />
        </section>

        <section class="panel">
          <h2>{{ 'Par salle' | t }}</h2>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">{{ 'Salle' | t }}</th>
                  <th scope="col">{{ 'Séances' | t }}</th>
                  <th scope="col">{{ 'Capacité' | t }}</th>
                  <th scope="col">{{ 'Réservations' | t }}</th>
                  <th scope="col">{{ 'Remplissage' | t }}</th>
                  <th scope="col">{{ 'Note' | t }}</th>
                  <th scope="col">
                    <span class="sr-only">{{ 'Actions' | t }}</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                @for (l of s.parSalle; track l.salle.id) {
                  <tr>
                    <td>
                      <a [routerLink]="['/salles', l.salle.id]">{{ l.salle.nom }}</a>
                    </td>
                    <td>{{ l.nbSeances }}</td>
                    <td>{{ l.capaciteTotale }}</td>
                    <td>{{ l.nbReservations }}</td>
                    <td>
                      <div
                        class="bar"
                        role="img"
                        [attr.aria-label]="
                          '{taux} % de remplissage' | t: { taux: l.tauxRemplissage ?? 0 }
                        "
                      >
                        <span [style.width.%]="l.tauxRemplissage ?? 0"></span>
                      </div>
                      <span class="muted">{{
                        l.tauxRemplissage !== null
                          ? (l.tauxRemplissage | nombreL: '1.0-1') + ' %'
                          : '—'
                      }}</span>
                    </td>
                    <td><app-etoiles [note]="l.noteMoyenne" [nbAvis]="l.nbAvis" /></td>
                    <td>
                      <div class="actions">
                        <a
                          class="btn btn--sm btn--ghost"
                          [routerLink]="['/gestionnaire/salles', l.salle.id]"
                          >{{ 'Modifier' | t }}</a
                        >
                      </div>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        </section>
      }
    }
  `,
})
export class TableauGestionnaireComponent {
  private readonly gestion = inject(GestionService);
  private readonly langue = inject(LangueService);
  readonly stats = signal<StatsGestionnaire | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    this.gestion.statsGestionnaire().subscribe({
      next: (s) => {
        this.stats.set(s);
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(
          messageErreur(error, this.langue.t('Impossible de charger le tableau de bord.')),
        );
        this.chargement.set(false);
      },
    });
  }
}
