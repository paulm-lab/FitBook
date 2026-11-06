import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Page, Reservation } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import {
  ConfirmService,
  EtatVideComponent,
  LoaderComponent,
  PaginationComponent,
} from '../../../shared/ui';

const STATUTS: Record<string, string> = {
  confirmee: 'Confirmée',
  annulee: 'Annulée',
  terminee: 'Terminée',
};

/** Toutes les réservations (US-A03) : filtres, annulation sans condition de délai. */
@Component({
  selector: 'app-reservations-admin',
  imports: [RouterLink, LoaderComponent, EtatVideComponent, PaginationComponent, ...I18N],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Administration' | t }}</span>
        <h1>{{ 'Réservations' | t }}</h1>
        <p>{{ 'Consultez et corrigez les réservations de la plateforme.' | t }}</p>
      </div>
    </header>

    <div class="row filters">
      <label class="field">
        <span>{{ 'Statut' | t }}</span>
        <select
          class="input"
          [value]="statut()"
          (change)="statut.set($any($event.target).value); charger(1)"
        >
          <option value="">{{ 'Tous' | t }}</option>
          @for (s of statuts; track s.code) {
            <option [value]="s.code">{{ s.libelle | t }}</option>
          }
        </select>
      </label>
      <label class="check">
        <input
          type="checkbox"
          [checked]="aVenir()"
          (change)="aVenir.set($any($event.target).checked); charger(1)"
        />
        {{ 'À venir uniquement' | t }}
      </label>
    </div>

    @if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (!page()) {
      <app-loader />
    } @else if (page()!.items.length === 0) {
      <app-etat-vide
        [titre]="'Aucune réservation' | t"
        [message]="'Aucune réservation ne correspond à ces filtres.' | t"
      />
    } @else {
      <p class="muted">
        {{
          (page()!.total === 1 ? '{n} réservation' : '{n} réservations') | t: { n: page()!.total }
        }}
      </p>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">{{ 'Séance' | t }}</th>
              <th scope="col">{{ 'Salle' | t }}</th>
              <th scope="col">{{ 'Sportif' | t }}</th>
              <th scope="col">{{ 'Statut' | t }}</th>
              <th scope="col">{{ 'Réservée le' | t }}</th>
              <th scope="col">
                <span class="sr-only">{{ 'Actions' | t }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            @for (r of page()!.items; track r.id) {
              <tr>
                <td>
                  @if (r.seance; as s) {
                    <a [routerLink]="['/seances', s.id]"
                      ><strong>{{ s.titre }}</strong></a
                    ><br />
                    <span class="muted">{{ s.dateDebut | dateL: 'EEE d MMM y, HH:mm' }}</span>
                  }
                </td>
                <td>{{ r.seance?.salle?.nom ?? '—' }}</td>
                <td>
                  @if (r.sportif; as p) {
                    {{ p.prenom }} {{ p.nom }}<br /><span class="muted">{{ p.email }}</span>
                  }
                </td>
                <td>
                  <span class="badge badge--{{ r.statut }}">{{ libelle(r.statut) | t }}</span>
                </td>
                <td>{{ r.dateReservation | dateL: 'd MMM y' }}</td>
                <td>
                  <div class="actions">
                    @if (r.statut === 'confirmee') {
                      <button
                        type="button"
                        class="btn btn--sm btn--danger"
                        [disabled]="enCours() === r.id"
                        (click)="annuler(r)"
                      >
                        {{ 'Annuler' | t }}
                      </button>
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
  styles: `
    .filters {
      align-items: end;
      gap: 20px;
      margin-bottom: 18px;
    }
    .check {
      min-height: 46px;
    }
  `,
})
export class ReservationsAdminComponent {
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly langue = inject(LangueService);

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
    this.gestion
      .reservationsAdmin({
        statut: this.statut(),
        periode: this.aVenir() ? 'a_venir' : undefined,
        page: numero,
      })
      .subscribe({
        next: (p) => this.page.set(p),
        error: (e) =>
          this.erreur.set(
            messageErreur(e, this.langue.t('Impossible de charger les réservations.')),
          ),
      });
  }

  async annuler(r: Reservation): Promise<void> {
    const ok = await this.confirm.demander({
      titre: this.langue.t('Annuler la réservation'),
      message: this.langue.t(
        'La réservation de {prenom} pour « {titre} » sera annulée, sans condition de délai. Le sportif sera prévenu par e-mail.',
        {
          prenom: r.sportif?.prenom ?? this.langue.t('ce sportif'),
          titre: r.seance?.titre ?? this.langue.t('la séance'),
        },
      ),
      confirmer: this.langue.t('Annuler la réservation'),
      danger: true,
    });
    if (!ok) return;
    this.enCours.set(r.id);
    this.gestion.annulerReservation(r.id).subscribe({
      next: (maj) => {
        this.enCours.set(null);
        this.page.update(
          (p) =>
            p && {
              ...p,
              items: p.items.map((i) =>
                i.id === maj.id ? { ...i, ...maj, sportif: maj.sportif ?? i.sportif } : i,
              ),
            },
        );
        this.toasts.succes(this.langue.t('Réservation annulée, le sportif est prévenu.'));
      },
      error: (e) => {
        this.enCours.set(null);
        this.toasts.erreur(messageErreur(e));
      },
    });
  }
}
