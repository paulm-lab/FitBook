import { DatePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { forkJoin } from 'rxjs';
import { AuthService } from '../../../core/auth/auth.service';
import { Reservation, SeanceResume } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { CalendrierComponent, evenementSeance } from '../../../shared/calendrier';
import { FavorisStore } from '../../../shared/favori-button';
import { EtatVideComponent, LoaderComponent } from '../../../shared/ui';

/** Tableau de bord du sportif (US-S04) : prochaines séances, compteurs, calendrier personnel. */
@Component({
  selector: 'app-tableau-sportif',
  imports: [RouterLink, DatePipe, CalendrierComponent, LoaderComponent, EtatVideComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Mon espace</span>
        <h1>Bonjour {{ auth.utilisateur()?.prenom }} !</h1>
        <p>Retrouvez vos prochaines séances et votre activité sur FitBook.</p>
      </div>
      <a class="btn" routerLink="/recherche">Trouver une séance</a>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else {
      <section class="stats" aria-label="Mes chiffres">
        <a class="stat" routerLink="/mon-espace/reservations"><span class="stat-value">{{ total() }}</span><span class="stat-label">Réservation{{ total() > 1 ? 's' : '' }} à venir</span></a>
        <a class="stat" routerLink="/mon-espace/favoris"><span class="stat-value">{{ favoris.favoris().length }}</span><span class="stat-label">Favoris</span></a>
        <a class="stat" routerLink="/mon-espace/avis"><span class="stat-value">{{ nbAvis() }}</span><span class="stat-label">Avis donnés</span></a>
      </section>

      <section class="panel" aria-labelledby="prochaines-title">
        <div class="row row--between">
          <h2 id="prochaines-title">Mes prochaines séances</h2>
          <a class="text-link" routerLink="/mon-espace/reservations">Toutes mes réservations →</a>
        </div>
        @if (prochaines().length === 0) {
          <app-etat-vide titre="Aucune séance réservée" message="Yoga, boxe, running… trouvez la séance qui vous motive." icone="📅">
            <a class="btn" routerLink="/recherche">Rechercher une séance</a>
          </app-etat-vide>
        } @else {
          <ul class="next-list">
            @for (r of prochaines(); track r.id) {
              @if (r.seance; as s) {
                <li>
                  <time class="next-date" [attr.datetime]="s.dateDebut">
                    <strong>{{ s.dateDebut | date: 'd' }}</strong><span>{{ s.dateDebut | date: 'MMM' }}</span>
                  </time>
                  <div class="next-info">
                    <a [routerLink]="['/mon-espace/reservations', r.id]">{{ s.titre }}</a>
                    <span class="muted">{{ s.dateDebut | date: 'EEEE · HH:mm' }} – {{ s.dateFin | date: 'HH:mm' }} · {{ s.salle?.nom ?? 'Lieu précisé par le coach' }}</span>
                  </div>
                  <span class="badge badge--confirmee">Confirmée</span>
                </li>
              }
            }
          </ul>
        }
      </section>

      <section class="calendar-section" aria-labelledby="calendrier-title">
        <h2 id="calendrier-title">Mon calendrier</h2>
        <app-calendrier [evenements]="evenements()" vue="dayGridMonth" (seanceCliquee)="ouvrir($event)" />
      </section>
    }
  `,
  styles: `
    .stats a { text-decoration: none; }
    .stats a:hover { border-color: var(--primary); }
    .panel { margin-bottom: 24px; }
    .panel h2 { margin: 0; }
    .next-list { display: grid; gap: 10px; margin: 16px 0 0; padding: 0; list-style: none; }
    .next-list li { display: flex; align-items: center; gap: 14px; padding: 12px; border: 1px solid var(--border); border-radius: 14px; }
    .next-date { display: grid; place-items: center; min-width: 56px; padding: 6px; border-radius: 12px; background: var(--primary-soft); color: var(--primary-hover); line-height: 1.1; }
    .next-date strong { font-size: 22px; }
    .next-date span { font-size: 12px; text-transform: uppercase; }
    .next-info { display: grid; flex: 1; gap: 2px; min-width: 0; }
    .next-info a { color: var(--ink); font-weight: 700; text-decoration: none; }
    .next-info a:hover { color: var(--primary); }
    .next-info .muted { font-size: 14px; }
    .calendar-section h2 { font-size: 21px; }
    @media (max-width: 520px) { .next-list li { flex-wrap: wrap; } }
  `,
})
export class TableauSportifComponent {
  readonly auth = inject(AuthService);
  readonly favoris = inject(FavorisStore);
  private readonly compte = inject(CompteService);
  private readonly router = inject(Router);

  readonly chargement = signal(true);
  readonly aVenir = signal<Reservation[]>([]);
  readonly total = signal(0);
  readonly nbAvis = signal(0);

  readonly prochaines = computed(() => this.aVenir().slice(0, 3));
  readonly evenements = computed(() =>
    this.aVenir().filter(r => r.seance).map(r => evenementSeance(r.seance as SeanceResume)),
  );

  constructor() {
    // 50 réservations à venir suffisent pour le calendrier personnel.
    forkJoin({ reservations: this.compte.reservations('a_venir', 1, 50), avis: this.compte.mesAvis() }).subscribe({
      next: ({ reservations, avis }) => {
        this.aVenir.set(reservations.items);
        this.total.set(reservations.total);
        this.nbAvis.set(avis.length);
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false),
    });
  }

  ouvrir(seance: SeanceResume): void {
    void this.router.navigate(['/seances', seance.id]);
  }
}
