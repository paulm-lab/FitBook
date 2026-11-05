import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { CoachDetail, Evolution, Note } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { GraphiqueEvolutionComponent } from '../../../shared/graphique';
import { EtatVideComponent, EtoilesComponent, LoaderComponent } from '../../../shared/ui';

interface StatsCoach {
  nbSeancesAVenir: number;
  nbInscritsAVenir: number;
  tauxRemplissage: number | null;
  note: Note;
  evolution: Evolution[];
}

/** Tableau de bord du coach (US-C06) : chiffres clés, évolution, prochaines séances. */
@Component({
  selector: 'app-tableau-coach',
  imports: [
    RouterLink,
    DatePipe,
    DecimalPipe,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    GraphiqueEvolutionComponent,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Espace coach</span>
        <h1>Bonjour {{ profil()?.prenom }} 👋</h1>
        <p>Votre activité en un coup d'œil.</p>
      </div>
      <a class="btn" routerLink="/coach/seances/nouvelle">+ Nouvelle séance</a>
    </header>

    @if (profilIncomplet()) {
      <p class="alert" role="status" style="margin-bottom:20px">
        Votre profil professionnel est incomplet : ajoutez une bio et vos spécialités pour être
        trouvé par les sportifs.
        <a routerLink="/coach/profil">Compléter mon profil →</a>
      </p>
    }

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (stats(); as s) {
      <div class="stats">
        <div class="stat">
          <span class="stat-value">{{ s.nbSeancesAVenir }}</span
          ><span class="stat-label">séances à venir</span>
        </div>
        <div class="stat">
          <span class="stat-value">{{ s.nbInscritsAVenir }}</span
          ><span class="stat-label">sportifs inscrits</span>
        </div>
        <div class="stat">
          <span class="stat-value">{{
            s.tauxRemplissage !== null ? (s.tauxRemplissage | number: '1.0-1') + ' %' : '—'
          }}</span
          ><span class="stat-label">taux de remplissage</span>
        </div>
        <div class="stat">
          <span class="stat-value">{{
            s.note.note !== null ? (s.note.note | number: '1.1-1') : '—'
          }}</span
          ><app-etoiles [note]="s.note.note" [nbAvis]="s.note.nbAvis" />
        </div>
      </div>

      <div class="grid grid--2" style="align-items:start">
        <section class="panel">
          <h2>Réservations par semaine</h2>
          <app-graphique-evolution [donnees]="s.evolution" />
        </section>
        <section class="panel">
          <div class="row row--between">
            <h2>Prochaines séances</h2>
            <a routerLink="/coach/seances" class="text-link">Tout voir →</a>
          </div>
          @if (prochaines().length === 0) {
            <app-etat-vide
              titre="Aucune séance programmée"
              message="Créez votre prochaine séance pour la rendre réservable."
              icone="📅"
            />
          } @else {
            <ul class="stack" style="list-style:none;margin:0;padding:0;gap:10px">
              @for (seance of prochaines(); track seance.id) {
                <li
                  class="row row--between"
                  style="padding:10px 0;border-bottom:1px solid var(--border)"
                >
                  <span>
                    <strong>{{ seance.titre }}</strong
                    ><br />
                    <span class="muted"
                      >{{ seance.dateDebut | date: 'EEE d MMM · HH:mm' }} ·
                      {{ seance.capaciteMax - seance.placesDisponibles }}/{{
                        seance.capaciteMax
                      }}
                      inscrits</span
                    >
                  </span>
                  <a
                    class="btn btn--sm btn--ghost"
                    [routerLink]="['/coach/seances', seance.id, 'inscrits']"
                    >Inscrits</a
                  >
                </li>
              }
            </ul>
          }
        </section>
      </div>
    }
  `,
})
export class TableauCoachComponent {
  private readonly gestion = inject(GestionService);
  readonly stats = signal<StatsCoach | null>(null);
  readonly profil = signal<CoachDetail | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  readonly prochaines = computed(() => (this.profil()?.prochainesSeances ?? []).slice(0, 5));
  readonly profilIncomplet = computed(() => {
    const p = this.profil();
    return p !== null && (!p.biographie || p.specialites.length === 0);
  });

  constructor() {
    this.gestion.profilCoach().subscribe({ next: (p) => this.profil.set(p) });
    this.gestion.statsCoach().subscribe({
      next: (s) => {
        this.stats.set(s);
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(messageErreur(error, 'Impossible de charger vos statistiques.'));
        this.chargement.set(false);
      },
    });
  }
}
