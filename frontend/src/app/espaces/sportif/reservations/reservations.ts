import { DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Page, Reservation } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { ToastService } from '../../../core/services/toast.service';
import {
  ConfirmService,
  EtatVideComponent,
  LoaderComponent,
  PaginationComponent,
} from '../../../shared/ui';

type Periode = 'a_venir' | 'historique';

export const STATUTS: Record<string, string> = {
  confirmee: 'Confirmée',
  annulee: 'Annulée',
  terminee: 'Terminée',
};

/**
 * Annule une réservation après confirmation (US-S03). Renvoie true si l'annulation a abouti.
 * Partagé par la liste et le détail.
 */
export async function annulerAvecConfirmation(
  reservation: Reservation,
  confirm: ConfirmService,
  compte: CompteService,
  toasts: ToastService,
  apres: (r: Reservation) => void,
): Promise<void> {
  const ok = await confirm.demander({
    titre: 'Annuler cette réservation ?',
    message: `« ${reservation.seance?.titre ?? 'Séance'} » : votre place sera libérée et vous recevrez un e-mail d'annulation.`,
    confirmer: 'Annuler la réservation',
    danger: true,
  });
  if (!ok) return;
  compte.annulerReservation(reservation.id).subscribe({
    next: (r) => {
      toasts.succes('Réservation annulée. Un e-mail de confirmation vous a été envoyé.');
      apres(r);
    },
    error: (error: unknown) => {
      // 409 : séance dans moins de 2 h, ou déjà annulée (RG-05).
      toasts.erreur(
        error instanceof HttpErrorResponse && error.status === 409
          ? messageErreur(
              error,
              'Il est trop tard pour annuler : la séance commence dans moins de 2 heures.',
            )
          : messageErreur(error, 'L’annulation n’a pas pu aboutir.'),
      );
    },
  });
}

/** Mes réservations : onglets « À venir » et « Historique » (US-S04), annulation (US-S03). */
@Component({
  selector: 'app-mes-reservations',
  imports: [
    RouterLink,
    DatePipe,
    DecimalPipe,
    LoaderComponent,
    EtatVideComponent,
    PaginationComponent,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Mon espace</span>
        <h1>Mes réservations</h1>
        <p>Annulation possible jusqu'à 2 heures avant le début de la séance.</p>
      </div>
    </header>

    <div class="tabs" role="tablist" aria-label="Période">
      <button
        type="button"
        class="tab"
        role="tab"
        [attr.aria-selected]="periode() === 'a_venir'"
        (click)="changerPeriode('a_venir')"
      >
        À venir
      </button>
      <button
        type="button"
        class="tab"
        role="tab"
        [attr.aria-selected]="periode() === 'historique'"
        (click)="changerPeriode('historique')"
      >
        Historique
      </button>
    </div>

    <div role="tabpanel">
      @if (chargement()) {
        <app-loader />
      } @else if (erreur()) {
        <p class="alert alert--error" role="alert">{{ erreur() }}</p>
      } @else if (page(); as p) {
        @if (p.items.length === 0) {
          <app-etat-vide
            [titre]="
              periode() === 'a_venir'
                ? 'Aucune réservation à venir'
                : 'Aucun historique pour le moment'
            "
            message="Réservez une séance en quelques clics depuis la recherche."
            icone="📅"
          >
            <a class="btn" routerLink="/recherche">Trouver une séance</a>
          </app-etat-vide>
        } @else {
          <div class="table-wrap">
            <table class="table">
              <caption class="sr-only">
                {{
                  periode() === 'a_venir' ? 'Réservations à venir' : 'Historique des réservations'
                }}
              </caption>
              <thead>
                <tr>
                  <th scope="col">Séance</th>
                  <th scope="col">Date</th>
                  <th scope="col">Lieu</th>
                  <th scope="col">Prix</th>
                  <th scope="col">Statut</th>
                  <th scope="col"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                @for (r of p.items; track r.id) {
                  <tr>
                    <td>
                      <a [routerLink]="['/mon-espace/reservations', r.id]"
                        ><strong>{{ r.seance?.titre }}</strong></a
                      ><br /><span class="muted">{{ r.seance?.activite?.nom }}</span>
                    </td>
                    <td>{{ r.seance?.dateDebut | date: 'EEE d MMM y · HH:mm' }}</td>
                    <td>{{ r.seance?.salle?.nom ?? 'Avec le coach' }}</td>
                    <td>
                      {{
                        +(r.seance?.prix ?? 0) === 0
                          ? 'Gratuit'
                          : (r.seance?.prix | number: '1.2-2') + ' €'
                      }}
                    </td>
                    <td>
                      <span class="badge badge--{{ r.statut }}">{{ statuts[r.statut] }}</span>
                    </td>
                    <td>
                      <div class="actions">
                        <a
                          class="btn btn--sm btn--ghost"
                          [routerLink]="['/mon-espace/reservations', r.id]"
                          >Détail</a
                        >
                        @if (r.annulable) {
                          <button
                            type="button"
                            class="btn btn--sm btn--danger"
                            (click)="annuler(r)"
                          >
                            Annuler
                          </button>
                        }
                      </div>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
          <app-pagination [page]="p.page" [pages]="p.pages" (changer)="charger($event)" />
        }
      }
    </div>
  `,
})
export class MesReservationsComponent {
  private readonly compte = inject(CompteService);
  private readonly confirm = inject(ConfirmService);
  private readonly toasts = inject(ToastService);

  readonly statuts = STATUTS;
  readonly periode = signal<Periode>('a_venir');
  readonly page = signal<Page<Reservation> | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    this.charger(1);
  }

  changerPeriode(periode: Periode): void {
    if (periode === this.periode()) return;
    this.periode.set(periode);
    this.charger(1);
  }

  charger(page: number): void {
    this.chargement.set(true);
    this.erreur.set('');
    this.compte.reservations(this.periode(), page).subscribe({
      next: (resultat) => {
        this.page.set(resultat);
        this.chargement.set(false);
      },
      error: (error: unknown) => {
        this.erreur.set(messageErreur(error, 'Vos réservations n’ont pas pu être chargées.'));
        this.chargement.set(false);
      },
    });
  }

  annuler(reservation: Reservation): void {
    void annulerAvecConfirmation(reservation, this.confirm, this.compte, this.toasts, () =>
      this.charger(this.page()?.page ?? 1),
    );
  }
}
