import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Page, Reservation } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { ConfirmService, EtatVideComponent, LoaderComponent, PaginationComponent } from '../../../shared/ui';

const STATUTS: Record<string, string> = { confirmee: 'Confirmée', annulee: 'Annulée', terminee: 'Terminée' };

/** Toutes les réservations (US-A03) : filtres, annulation sans condition de délai. */
@Component({
  selector: 'app-reservations-admin',
  imports: [DatePipe, RouterLink, LoaderComponent, EtatVideComponent, PaginationComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Administration</span>
        <h1>Réservations</h1>
        <p>Consultez et corrigez les réservations de la plateforme.</p>
      </div>
    </header>

    <div class="row filters">
      <label class="field">
        <span>Statut</span>
        <select class="input" [value]="statut()" (change)="statut.set($any($event.target).value); charger(1)">
          <option value="">Tous</option>
          @for (s of statuts; track s.code) { <option [value]="s.code">{{ s.libelle }}</option> }
        </select>
      </label>
      <label class="check">
        <input type="checkbox" [checked]="aVenir()" (change)="aVenir.set($any($event.target).checked); charger(1)">
        À venir uniquement
      </label>
    </div>

    @if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (!page()) {
      <app-loader />
    } @else if (page()!.items.length === 0) {
      <app-etat-vide titre="Aucune réservation" message="Aucune réservation ne correspond à ces filtres." />
    } @else {
      <p class="muted">{{ page()!.total }} réservation(s)</p>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Séance</th><th scope="col">Salle</th><th scope="col">Sportif</th>
              <th scope="col">Statut</th><th scope="col">Réservée le</th><th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @for (r of page()!.items; track r.id) {
              <tr>
                <td>
                  @if (r.seance; as s) {
                    <a [routerLink]="['/seances', s.id]"><strong>{{ s.titre }}</strong></a><br>
                    <span class="muted">{{ s.dateDebut | date: 'EEE d MMM y, HH:mm' }}</span>
                  }
                </td>
                <td>{{ r.seance?.salle?.nom ?? '—' }}</td>
                <td>
                  @if (r.sportif; as p) { {{ p.prenom }} {{ p.nom }}<br><span class="muted">{{ p.email }}</span> }
                </td>
                <td><span class="badge badge--{{ r.statut }}">{{ libelle(r.statut) }}</span></td>
                <td>{{ r.dateReservation | date: 'd MMM y' }}</td>
                <td>
                  <div class="actions">
                    @if (r.statut === 'confirmee') {
                      <button type="button" class="btn btn--sm btn--danger" [disabled]="enCours() === r.id" (click)="annuler(r)">Annuler</button>
                    }
                  </div>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
      <app-pagination [page]="page()!.page" [pages]="page()!.pages" (changer)="charger($event)" />
    }
  `,
  styles: `.filters { align-items: end; gap: 20px; margin-bottom: 18px; } .check { min-height: 46px; }`,
})
export class ReservationsAdminComponent {
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  readonly statuts = Object.entries(STATUTS).map(([code, libelle]) => ({ code, libelle }));
  readonly statut = signal('');
  readonly aVenir = signal(false);
  readonly page = signal<Page<Reservation> | null>(null);
  readonly erreur = signal('');
  readonly enCours = signal<number | null>(null);

  constructor() {
    this.charger(1);
  }

  libelle(statut: string): string {
    return STATUTS[statut] ?? statut;
  }

  charger(numero: number): void {
    this.erreur.set('');
    this.gestion.reservationsAdmin({ statut: this.statut(), periode: this.aVenir() ? 'a_venir' : undefined, page: numero }).subscribe({
      next: p => this.page.set(p),
      error: e => this.erreur.set(messageErreur(e, 'Impossible de charger les réservations.')),
    });
  }

  async annuler(r: Reservation): Promise<void> {
    const ok = await this.confirm.demander({
      titre: 'Annuler la réservation',
      message: `La réservation de ${r.sportif?.prenom ?? 'ce sportif'} pour « ${r.seance?.titre ?? 'la séance'} » sera annulée, sans condition de délai. Le sportif sera prévenu par e-mail.`,
      confirmer: 'Annuler la réservation',
      danger: true,
    });
    if (!ok) return;
    this.enCours.set(r.id);
    this.gestion.annulerReservation(r.id).subscribe({
      next: maj => {
        this.enCours.set(null);
        this.page.update(p => p && { ...p, items: p.items.map(i => (i.id === maj.id ? { ...i, ...maj, sportif: maj.sportif ?? i.sportif } : i)) });
        this.toasts.succes('Réservation annulée, le sportif est prévenu.');
      },
      error: e => {
        this.enCours.set(null);
        this.toasts.erreur(messageErreur(e));
      },
    });
  }
}
