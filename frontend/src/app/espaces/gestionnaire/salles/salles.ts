import { DecimalPipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Salle, StatsSalle } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { EtatVideComponent, EtoilesComponent, ImageSecoursDirective, LoaderComponent } from '../../../shared/ui';

/** Mes salles (US-G01) : cartes avec leurs statistiques et leurs actions. */
@Component({
  selector: 'app-mes-salles',
  imports: [RouterLink, DecimalPipe, LoaderComponent, EtatVideComponent, EtoilesComponent, ImageSecoursDirective],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Mes salles</span>
        <h1>Salles gérées</h1>
        <p>Informations, horaires, photos et cours de chacune de vos salles.</p>
      </div>
      <a class="btn" routerLink="/gestionnaire/salles/nouvelle">+ Nouvelle salle</a>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (salles().length === 0) {
      <app-etat-vide titre="Aucune salle" message="Créez votre première salle pour y programmer des cours." icone="🏋">
        <a class="btn btn--sm" routerLink="/gestionnaire/salles/nouvelle">Créer une salle</a>
      </app-etat-vide>
    } @else {
      <div class="grid">
        @for (s of salles(); track s.id) {
          <article class="card">
            <div class="card-media"><img [src]="s.image || '/assets/fitbook-media-fallback.svg'" alt="" loading="lazy" appSecours></div>
            <div class="card-body">
              <span class="card-kicker">{{ s.ville }}</span>
              <h2 class="card-title">{{ s.nom }}</h2>
              <p class="card-meta">{{ s.adresse }}</p>
              <app-etoiles [note]="s.stats.noteMoyenne" [nbAvis]="s.stats.nbAvis" />
              <p class="card-meta">
                {{ s.stats.nbSeances }} séance(s) · {{ s.stats.nbReservations }} réservation(s)
                · {{ s.stats.tauxRemplissage !== null ? (s.stats.tauxRemplissage | number: '1.0-1') + ' % rempli' : 'pas encore de séance' }}
              </p>
              <div class="row" style="margin-top:auto;padding-top:10px;gap:6px">
                <a class="btn btn--sm" [routerLink]="['/gestionnaire/salles', s.id]">Modifier</a>
                <a class="btn btn--sm btn--ghost" routerLink="/gestionnaire/seances" [queryParams]="{ salleId: s.id }">Séances</a>
                <a class="btn btn--sm btn--ghost" [routerLink]="['/salles', s.id]">Fiche publique</a>
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
  readonly salles = signal<(Salle & { stats: StatsSalle })[]>([]);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    this.gestion.mesSalles().subscribe({
      next: s => {
        this.salles.set(s);
        this.chargement.set(false);
      },
      error: error => {
        this.erreur.set(messageErreur(error, 'Impossible de charger vos salles.'));
        this.chargement.set(false);
      },
    });
  }
}
