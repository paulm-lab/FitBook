import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Evolution, ROLES, Role } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { GraphiqueEvolutionComponent } from '../../../shared/graphique';
import { LoaderComponent } from '../../../shared/ui';

type StatsAdmin = {
  utilisateursParRole: Record<string, number>; nbUtilisateurs: number; nbSalles: number; nbActivites: number;
  nbSeances: number; nbSeancesAVenir: number; reservationsParStatut: Record<string, number>; avisEnAttente: number; evolution: Evolution[];
};

const STATUTS: Record<string, string> = { confirmee: 'Confirmées', annulee: 'Annulées', terminee: 'Terminées' };

/** Tableau de bord global de l'administrateur (US-A01). */
@Component({
  selector: 'app-tableau-admin',
  imports: [RouterLink, LoaderComponent, GraphiqueEvolutionComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Administration</span>
        <h1>Vue d'ensemble</h1>
        <p>Surveillez l'activité de la plateforme FitBook.</p>
      </div>
    </header>

    @if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (!stats()) {
      <app-loader />
    } @else {
      @let s = stats()!;
      <div class="stats">
        <a class="stat tile-link" routerLink="/admin/utilisateurs"><span class="stat-value">{{ s.nbUtilisateurs }}</span><span class="stat-label">Utilisateurs</span></a>
        <a class="stat tile-link" routerLink="/admin/salles"><span class="stat-value">{{ s.nbSalles }}</span><span class="stat-label">Salles</span></a>
        <a class="stat tile-link" routerLink="/admin/activites"><span class="stat-value">{{ s.nbActivites }}</span><span class="stat-label">Activités</span></a>
        <a class="stat tile-link" routerLink="/admin/seances"><span class="stat-value">{{ s.nbSeancesAVenir }}</span><span class="stat-label">Séances à venir (sur {{ s.nbSeances }})</span></a>
        <a class="stat tile-link" [class.tile-alert]="s.avisEnAttente > 0" routerLink="/admin/avis" [queryParams]="{ filtre: 'non_moderes' }">
          <span class="stat-value">{{ s.avisEnAttente }}</span><span class="stat-label">Avis en attente de modération</span>
        </a>
      </div>

      <div class="grid--2 grid">
        <section class="panel" aria-labelledby="roles-titre">
          <h2 id="roles-titre">Utilisateurs par rôle</h2>
          <ul class="bars">
            @for (r of roles(); track r.code) {
              <li>
                <span class="bar-label">{{ r.libelle }}</span>
                <span class="bar-track" aria-hidden="true"><span class="bar-fill" [style.width.%]="r.pourcent"></span></span>
                <strong>{{ r.nombre }}</strong>
              </li>
            }
          </ul>
        </section>

        <section class="panel" aria-labelledby="resa-titre">
          <h2 id="resa-titre">Réservations par statut</h2>
          <ul class="bars">
            @for (r of statuts(); track r.code) {
              <li>
                <span class="bar-label"><span class="badge badge--{{ r.code }}">{{ r.libelle }}</span></span>
                <span class="bar-track" aria-hidden="true"><span class="bar-fill" [style.width.%]="r.pourcent"></span></span>
                <strong>{{ r.nombre }}</strong>
              </li>
            }
          </ul>
          <a class="text-link" routerLink="/admin/reservations">Voir les réservations →</a>
        </section>
      </div>

      <section class="panel evo" aria-labelledby="evo-titre">
        <h2 id="evo-titre">Évolution des réservations</h2>
        <app-graphique-evolution [donnees]="s.evolution" />
      </section>
    }
  `,
  styles: `
    .tile-link { text-decoration: none; transition: border-color .2s; }
    .tile-link:hover { border-color: var(--primary); }
    .tile-alert { border-color: var(--primary); background: var(--primary-soft); }
    .bars { display: grid; gap: 12px; margin: 0 0 14px; padding: 0; list-style: none; }
    .bars li { display: grid; grid-template-columns: 150px 1fr 48px; align-items: center; gap: 12px; color: var(--ink); }
    .bars strong { text-align: right; }
    .bar-label { color: var(--text); font-size: 14px; font-weight: 700; }
    .bar-track { height: 10px; overflow: hidden; border-radius: 999px; background: var(--surface-alt); }
    .bar-fill { display: block; height: 100%; border-radius: 999px; background: var(--primary); }
    .evo { margin-top: 22px; }
    @media (max-width: 520px) { .bars li { grid-template-columns: 110px 1fr 40px; } }
  `,
})
export class TableauAdminComponent {
  private readonly gestion = inject(GestionService);
  readonly stats = signal<StatsAdmin | null>(null);
  readonly erreur = signal('');

  readonly roles = computed(() => {
    const s = this.stats();
    if (!s) return [];
    const max = Math.max(1, ...Object.values(s.utilisateursParRole));
    return (Object.keys(ROLES) as Role[]).map(code => {
      const nombre = s.utilisateursParRole[code] ?? 0;
      return { code, libelle: ROLES[code], nombre, pourcent: (100 * nombre) / max };
    });
  });

  readonly statuts = computed(() => {
    const s = this.stats();
    if (!s) return [];
    const max = Math.max(1, ...Object.values(s.reservationsParStatut));
    return Object.keys(STATUTS).map(code => {
      const nombre = s.reservationsParStatut[code] ?? 0;
      return { code, libelle: STATUTS[code], nombre, pourcent: (100 * nombre) / max };
    });
  });

  constructor() {
    this.gestion.statsAdmin().subscribe({
      next: s => this.stats.set(s),
      error: e => this.erreur.set(messageErreur(e, 'Impossible de charger les statistiques.')),
    });
  }
}
