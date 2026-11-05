import { Component, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { EventInput } from '@fullcalendar/core';
import { messageErreur } from '../../../core/http';
import { Disponibilite, Salle, SeanceResume } from '../../../core/models';
import { CatalogueService } from '../../../core/services/catalogue.service';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { CalendrierComponent, evenementSeance, evenementsDisponibilites } from '../../../shared/calendrier';
import { dateLocale, espaceCourant } from '../espace';

/** Calendrier des séances et disponibilités de l'espace (US-C04). */
@Component({
  selector: 'app-calendrier-espace',
  imports: [CalendrierComponent],
  styles: `
    .legend { display: flex; flex-wrap: wrap; gap: 16px; margin: 0 0 16px; padding: 0; list-style: none; color: var(--text); font-size: 14px; }
    .legend li { display: flex; align-items: center; gap: 6px; }
    .legend span { width: 14px; height: 14px; border-radius: 4px; }
  `,
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Calendrier</span>
        <h1>Mon planning</h1>
        <p>Cliquez sur une séance pour voir les inscrits, sur un jour pour créer une séance.</p>
      </div>
      @if (espace === 'gestionnaire') {
        <label class="field" style="min-width:220px">
          <span>Salle</span>
          <select [value]="salleId() ?? ''" (change)="changerSalle($any($event.target).value)">
            <option value="">Toutes mes salles</option>
            @for (s of salles(); track s.id) { <option [value]="s.id">{{ s.nom }}</option> }
          </select>
        </label>
      }
    </header>

    <ul class="legend" aria-label="Légende">
      <li><span style="background:#ff642d"></span>Séance avec places</li>
      <li><span style="background:#10213a"></span>Complète</li>
      <li><span style="background:#9aa4b2"></span>Annulée</li>
      <li><span style="background:rgba(22,112,68,.35)"></span>Disponibilité</li>
    </ul>

    <app-calendrier [evenements]="evenements()" (periode)="charger($event)" (seanceCliquee)="ouvrir($event)" (jourClique)="nouvelle($event)" />
  `,
})
export class CalendrierEspaceComponent {
  private readonly gestion = inject(GestionService);
  private readonly catalogue = inject(CatalogueService);
  private readonly toasts = inject(ToastService);
  private readonly router = inject(Router);

  readonly espace = espaceCourant();
  readonly seances = signal<SeanceResume[]>([]);
  readonly disponibilites = signal<Disponibilite[]>([]);
  readonly salles = signal<Salle[]>([]);
  readonly salleId = signal<number | null>(null);
  private fenetre: { debut: Date; fin: Date } | null = null;

  readonly evenements = computed<EventInput[]>(() => [
    ...this.seances().map(evenementSeance),
    ...evenementsDisponibilites(this.disponibilites()),
  ]);

  constructor() {
    if (this.espace === 'coach') {
      this.gestion.profilCoach().subscribe({ next: p => this.disponibilites.set(p.disponibilites) });
    } else if (this.espace === 'gestionnaire') {
      this.gestion.mesSalles().subscribe({ next: s => this.salles.set(s) });
    }
  }

  changerSalle(valeur: string): void {
    this.salleId.set(valeur ? Number(valeur) : null);
    const id = this.salleId();
    if (id) {
      this.catalogue.disponibilites({ salleId: id }).subscribe({ next: d => this.disponibilites.set(d) });
    } else {
      this.disponibilites.set([]);
    }
    if (this.fenetre) this.charger(this.fenetre);
  }

  charger(fenetre: { debut: Date; fin: Date }): void {
    this.fenetre = fenetre;
    this.gestion.seances(this.espace, { debut: fenetre.debut.toISOString(), fin: fenetre.fin.toISOString(), salleId: this.salleId() ?? undefined }).subscribe({
      next: reponse => this.seances.set(Array.isArray(reponse) ? reponse : reponse.items),
      error: error => this.toasts.erreur(messageErreur(error, 'Impossible de charger le calendrier.')),
    });
  }

  ouvrir(seance: SeanceResume): void {
    void this.router.navigate([`/${this.espace}/seances`, seance.id, 'inscrits']);
  }

  nouvelle(jour: Date): void {
    if (jour < new Date(new Date().setHours(0, 0, 0, 0))) return;
    void this.router.navigate([`/${this.espace}/seances/nouvelle`], { queryParams: { date: dateLocale(jour) } });
  }
}
