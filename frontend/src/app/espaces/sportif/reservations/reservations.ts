import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
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
  langue: LangueService,
  apres: (r: Reservation) => void,
): Promise<void> {
  const t = langue.t.bind(langue);
  const ok = await confirm.demander({
    titre: t('Annuler cette réservation ?'),
    message: t("« {titre} » : votre place sera libérée et vous recevrez un e-mail d'annulation.", {
      titre: reservation.seance?.titre ?? t('Séance'),
    }),
    confirmer: t('Annuler la réservation'),
    danger: true,
  });
  if (!ok) return;
  compte.annulerReservation(reservation.id).subscribe({
    next: (r) => {
      toasts.succes(t('Réservation annulée. Un e-mail de confirmation vous a été envoyé.'));
      apres(r);
    },
    error: (error: unknown) => {
      // 409 : séance dans moins de 2 h, ou déjà annulée (RG-05).
      toasts.erreur(
        error instanceof HttpErrorResponse && error.status === 409
          ? messageErreur(
              error,
              t('Il est trop tard pour annuler : la séance commence dans moins de 2 heures.'),
            )
          : messageErreur(error, t('L’annulation n’a pas pu aboutir.')),
      );
    },
  });
}

/** Mes réservations : onglets « À venir » et « Historique » (US-S04), annulation (US-S03). */
@Component({
  selector: 'app-mes-reservations',
  imports: [RouterLink, LoaderComponent, EtatVideComponent, PaginationComponent, ...I18N],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Mon espace' | t }}</span>
        <h1>{{ 'Mes réservations' | t }}</h1>
        <p>{{ delaiAnnulation | t }}</p>
      </div>
    </header>

    <div class="tabs" role="tablist" [attr.aria-label]="'Période' | t">
      <button
        type="button"
        class="tab"
        role="tab"
        [attr.aria-selected]="periode() === 'a_venir'"
        (click)="changerPeriode('a_venir')"
      >
        {{ 'À venir' | t }}
      </button>
      <button
        type="button"
        class="tab"
        role="tab"
        [attr.aria-selected]="periode() === 'historique'"
        (click)="changerPeriode('historique')"
      >
        {{ 'Historique' | t }}
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
              (periode() === 'a_venir'
                ? 'Aucune réservation à venir'
                : 'Aucun historique pour le moment'
              ) | t
            "
            [message]="'Réservez une séance en quelques clics depuis la recherche.' | t"
            icone="📅"
          >
            <a class="btn" routerLink="/recherche">{{ 'Trouver une séance' | t }}</a>
          </app-etat-vide>
        } @else {
          <div class="table-wrap">
            <table class="table">
              <caption class="sr-only">
                {{
                  (periode() === 'a_venir' ? 'Réservations à venir' : 'Historique des réservations')
                    | t
                }}
              </caption>
              <thead>
                <tr>
                  <th scope="col">{{ 'Séance' | t }}</th>
                  <th scope="col">{{ 'Date' | t }}</th>
                  <th scope="col">{{ 'Lieu' | t }}</th>
                  <th scope="col">{{ 'Prix' | t }}</th>
                  <th scope="col">{{ 'Statut' | t }}</th>
                  <th scope="col">
                    <span class="sr-only">{{ 'Actions' | t }}</span>
                  </th>
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
                    <td>{{ r.seance?.dateDebut | dateL: 'EEE d MMM y · HH:mm' }}</td>
                    <td>{{ r.seance?.salle?.nom ?? ('Avec le coach' | t) }}</td>
                    <td>
                      {{
                        +(r.seance?.prix ?? 0) === 0
                          ? ('Gratuit' | t)
                          : ('{prix} €' | t: { prix: r.seance?.prix | nombreL: '1.2-2' })
                      }}
                    </td>
                    <td>
                      <span class="badge badge--{{ r.statut }}">{{ statuts[r.statut] | t }}</span>
                    </td>
                    <td>
                      <div class="actions">
                        <a
                          class="btn btn--sm btn--ghost"
                          [routerLink]="['/mon-espace/reservations', r.id]"
                          >{{ 'Détail' | t }}</a
                        >
                        @if (r.annulable) {
                          <button
                            type="button"
                            class="btn btn--sm btn--danger"
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
  private readonly langue = inject(LangueService);

  readonly statuts = STATUTS;
  /** Texte avec apostrophe, tenu hors du gabarit (guillemets du parseur Angular). */
  readonly delaiAnnulation = "Annulation possible jusqu'à 2 heures avant le début de la séance.";
  readonly periode = signal<Periode>('a_venir');
  readonly page = signal<Page<Reservation> | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');
  private requete?: Subscription;

  constructor() {
    this.charger(1);
  }

  changerPeriode(periode: Periode): void {
    if (periode === this.periode()) return;
    this.periode.set(periode);
    this.charger(1);
  }

  /** @param repli page vide après une annulation : on recule d'une page */
  charger(page: number, repli = false): void {
    this.chargement.set(true);
    this.erreur.set('');
    // Une réponse lente de l'onglet précédent n'écrase pas la vue courante.
    this.requete?.unsubscribe();
    this.requete = this.compte.reservations(this.periode(), page).subscribe({
      next: (resultat) => {
        if (repli && page > 1 && resultat.items.length === 0) {
          this.charger(page - 1);
          return;
        }
        this.page.set(resultat);
        this.chargement.set(false);
      },
      error: (error: unknown) => {
        this.erreur.set(
          messageErreur(error, this.langue.t('Vos réservations n’ont pas pu être chargées.')),
        );
        this.chargement.set(false);
      },
    });
  }

  annuler(reservation: Reservation): void {
    void annulerAvecConfirmation(
      reservation,
      this.confirm,
      this.compte,
      this.toasts,
      this.langue,
      () => this.charger(this.page()?.page ?? 1, true),
    );
  }
}
