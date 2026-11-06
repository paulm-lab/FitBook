import { Component, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { QRCodeComponent } from 'angularx-qrcode';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { NIVEAUX, Reservation } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { ToastService } from '../../../core/services/toast.service';
import { ConfirmService, LoaderComponent } from '../../../shared/ui';
import { STATUTS, annulerAvecConfirmation } from '../reservations/reservations';

/** Détail d'une réservation avec son QR code à présenter à l'accueil (bonus US-S09). */
@Component({
  selector: 'app-reservation-detail',
  imports: [RouterLink, QRCodeComponent, LoaderComponent, ...I18N],
  template: `
    <header class="page-header no-print">
      <div>
        <a class="text-link" routerLink="/mon-espace/reservations">{{
          '← Mes réservations' | t
        }}</a>
        <h1>{{ 'Ma réservation' | t }}</h1>
      </div>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (reservation(); as r) {
      @let s = r.seance;
      <div class="detail">
        <section class="panel">
          <div class="row row--between">
            <span class="badge badge--{{ r.statut }}">{{ statuts[r.statut] | t }}</span>
            <span class="muted">{{
              'Réservation n° {id} · le {date}'
                | t: { id: r.id, date: r.dateReservation | dateL: 'd MMM y' }
            }}</span>
          </div>
          @if (s) {
            <h2>{{ s.titre }}</h2>
            <dl class="infos">
              <div>
                <dt>{{ 'Date' | t }}</dt>
                <dd>{{ s.dateDebut | dateL: 'EEEE d MMMM y' }}</dd>
              </div>
              <div>
                <dt>{{ 'Horaire' | t }}</dt>
                <dd>{{ s.dateDebut | dateL: 'HH:mm' }} – {{ s.dateFin | dateL: 'HH:mm' }}</dd>
              </div>
              <div>
                <dt>{{ 'Activité' | t }}</dt>
                <dd>{{ s.activite?.nom }} · {{ niveaux[s.niveau] | t }}</dd>
              </div>
              <div>
                <dt>{{ 'Lieu' | t }}</dt>
                <dd>
                  {{
                    s.salle ? s.salle.nom + ', ' + s.salle.adresse : ('Précisé par le coach' | t)
                  }}
                </dd>
              </div>
              @if (s.coach) {
                <div>
                  <dt>{{ 'Coach' | t }}</dt>
                  <dd>{{ s.coach.prenom }} {{ s.coach.nom }}</dd>
                </div>
              }
              <div>
                <dt>{{ 'Prix' | t }}</dt>
                <dd>
                  {{
                    +s.prix === 0
                      ? ('Gratuit' | t)
                      : ('{prix} € (réglé sur place)' | t: { prix: s.prix | nombreL: '1.2-2' })
                  }}
                </dd>
              </div>
            </dl>
            <!-- Séance terminée : la fiche reste accessible au participant pour donner son avis. -->
            @if (finie(r)) {
              <a
                class="text-link no-print"
                [routerLink]="['/seances', s.id]"
                fragment="avis-titre"
                >{{ 'Donner mon avis sur la séance →' | t }}</a
              >
            } @else {
              <a class="text-link no-print" [routerLink]="['/seances', s.id]">{{
                'Voir la fiche de la séance →' | t
              }}</a>
            }
          }
          @if (r.dateAnnulation) {
            <p class="muted">
              {{ 'Annulée le {date}.' | t: { date: r.dateAnnulation | dateL: dateHeure() } }}
            </p>
          }
          <div class="row no-print actions">
            @if (r.annulable) {
              <button type="button" class="btn btn--danger" (click)="annuler(r)">
                {{ 'Annuler la réservation' | t }}
              </button>
            } @else if (r.statut === 'confirmee') {
              <p class="muted">
                {{ textes.tropTard | t }}
              </p>
            }
          </div>
        </section>

        @if (r.statut === 'confirmee' && r.qrToken) {
          <section class="panel qr" aria-labelledby="qr-title">
            <h2 id="qr-title">{{ textes.qrTitre | t }}</h2>
            <p class="muted">{{ textes.qrAide | t }}</p>
            <qrcode
              [qrdata]="'FITBOOK:' + r.qrToken"
              [width]="220"
              [errorCorrectionLevel]="'M'"
              [ariaLabel]="'QR code de la réservation {id}' | t: { id: r.id }"
            />
            <button type="button" class="btn btn--ghost no-print" (click)="imprimer()">
              {{ 'Imprimer' | t }}
            </button>
          </section>
        }
      </div>
    }
  `,
  styles: `
    .detail {
      display: grid;
      grid-template-columns: minmax(0, 1.4fr) minmax(260px, 1fr);
      gap: 20px;
      align-items: start;
    }
    .panel h2 {
      margin: 14px 0 10px;
    }
    .infos {
      display: grid;
      gap: 10px;
      margin: 0 0 16px;
    }
    .infos div {
      display: grid;
      grid-template-columns: 110px 1fr;
      gap: 10px;
    }
    .infos dt {
      color: var(--muted);
      font-size: 14px;
    }
    .infos dd {
      margin: 0;
      color: var(--ink);
      font-weight: 600;
    }
    .actions {
      margin-top: 16px;
    }
    .qr {
      display: grid;
      justify-items: center;
      gap: 10px;
      text-align: center;
    }
    .qr h2 {
      margin: 0;
    }
    .qr qrcode {
      padding: 10px;
      border-radius: 12px;
      background: #fff;
    }
    @media (max-width: 900px) {
      .detail {
        grid-template-columns: 1fr;
      }
    }
    @media print {
      .detail {
        grid-template-columns: 1fr;
      }
      .panel {
        box-shadow: none;
      }
    }
  `,
})
export class ReservationDetailComponent {
  /** Paramètre de route :id (withComponentInputBinding). */
  readonly id = input.required<string>();

  private readonly compte = inject(CompteService);
  private readonly confirm = inject(ConfirmService);
  private readonly toasts = inject(ToastService);
  private readonly langue = inject(LangueService);

  readonly statuts = STATUTS;
  /** Textes contenant une apostrophe, tenus hors du gabarit (guillemets du parseur Angular). */
  readonly textes = {
    tropTard: "L'annulation n'est plus possible à moins de 2 heures du début.",
    qrTitre: "QR code d'accès",
    qrAide: "Présentez ce code à l'accueil le jour de la séance.",
  };
  readonly niveaux = NIVEAUX;
  readonly reservation = signal<Reservation | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    effect(() => this.charger(Number(this.id())));
  }

  annuler(reservation: Reservation): void {
    void annulerAvecConfirmation(
      reservation,
      this.confirm,
      this.compte,
      this.toasts,
      this.langue,
      (r) => this.reservation.set(r),
    );
  }

  /** Participation terminée (statut « terminée » ou séance finie, le statut suit par tâche planifiée). */
  finie(r: Reservation): boolean {
    return (
      r.statut === 'terminee' ||
      (r.statut === 'confirmee' && !!r.seance && new Date(r.seance.dateFin) <= new Date())
    );
  }

  /** Format date + heure dans la langue courante (« à » / « at »). */
  dateHeure(): string {
    return this.langue.langue() === 'en' ? "d MMM y 'at' HH:mm" : "d MMM y 'à' HH:mm";
  }

  imprimer(): void {
    window.print();
  }

  private charger(id: number): void {
    this.chargement.set(true);
    this.compte.reservation(id).subscribe({
      next: (r) => {
        this.reservation.set(r);
        this.chargement.set(false);
      },
      error: (error: unknown) => {
        this.erreur.set(messageErreur(error, this.langue.t('Cette réservation est introuvable.')));
        this.chargement.set(false);
      },
    });
  }
}
