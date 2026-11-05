import { DecimalPipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
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
    DecimalPipe,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    GraphiqueEvolutionComponent,
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
        <span class="section-kicker">Espace gestionnaire</span>
        <h1>Tableau de bord</h1>
        <p>Réservations, remplissage et satisfaction de vos salles.</p>
      </div>
      <div class="row">
        <a class="btn btn--ghost" routerLink="/gestionnaire/seances/nouvelle">+ Nouvelle séance</a>
        <a class="btn" routerLink="/gestionnaire/salles/nouvelle">+ Nouvelle salle</a>
      </div>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (stats(); as s) {
      @if (s.nbSalles === 0) {
        <app-etat-vide
          titre="Créez votre première salle"
          message="Ajoutez votre salle pour y programmer des cours et suivre vos réservations."
          icone="🏋"
        >
          <a class="btn btn--sm" routerLink="/gestionnaire/salles/nouvelle">Créer une salle</a>
        </app-etat-vide>
      } @else {
        <div class="stats">
          <div class="stat">
            <span class="stat-value">{{ s.nbSalles }}</span
            ><span class="stat-label">salle(s)</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{ s.nbSeances }}</span
            ><span class="stat-label">séances actives</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{ s.nbReservations }}</span
            ><span class="stat-label">réservations</span>
          </div>
          <div class="stat">
            <span class="stat-value">{{
              s.tauxRemplissage !== null ? (s.tauxRemplissage | number: '1.0-1') + ' %' : '—'
            }}</span
            ><span class="stat-label">taux de remplissage</span>
          </div>
        </div>

        <section class="panel" style="margin-bottom:24px">
          <h2>Réservations par semaine</h2>
          <app-graphique-evolution [donnees]="s.evolution" />
        </section>

        <section class="panel">
          <h2>Par salle</h2>
          <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th scope="col">Salle</th>
                  <th scope="col">Séances</th>
                  <th scope="col">Capacité</th>
                  <th scope="col">Réservations</th>
                  <th scope="col">Remplissage</th>
                  <th scope="col">Note</th>
                  <th scope="col"><span class="sr-only">Actions</span></th>
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
                        [attr.aria-label]="(l.tauxRemplissage ?? 0) + ' % de remplissage'"
                      >
                        <span [style.width.%]="l.tauxRemplissage ?? 0"></span>
                      </div>
                      <span class="muted">{{
                        l.tauxRemplissage !== null
                          ? (l.tauxRemplissage | number: '1.0-1') + ' %'
                          : '—'
                      }}</span>
                    </td>
                    <td><app-etoiles [note]="l.noteMoyenne" [nbAvis]="l.nbAvis" /></td>
                    <td>
                      <div class="actions">
                        <a
                          class="btn btn--sm btn--ghost"
                          [routerLink]="['/gestionnaire/salles', l.salle.id]"
                          >Modifier</a
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
        this.erreur.set(messageErreur(error, 'Impossible de charger le tableau de bord.'));
        this.chargement.set(false);
      },
    });
  }
}
