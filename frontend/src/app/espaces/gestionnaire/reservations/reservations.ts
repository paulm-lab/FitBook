import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { Page, Reservation } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { EtatVideComponent, LoaderComponent, PaginationComponent } from '../../../shared/ui';

const STATUTS: Record<string, string> = {
  confirmee: 'Confirmée',
  annulee: 'Annulée',
  terminee: 'Terminée',
};

/** Réservations des séances de mes salles (US-G04). */
@Component({
  selector: 'app-reservations-gestionnaire',
  imports: [RouterLink, LoaderComponent, EtatVideComponent, PaginationComponent, ...I18N],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Réservations' | t }}</span>
        <h1>{{ 'Réservations de mes salles' | t }}</h1>
        <p>{{ 'Préparez l’accueil des sportifs.' | t }}</p>
      </div>
    </header>

    <div class="row" style="margin-bottom:16px">
      <div class="tabs" role="tablist" [attr.aria-label]="'Période' | t" style="margin:0">
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="periode() === 'a_venir'"
          (click)="periode.set('a_venir'); charger(1)"
        >
          {{ 'À venir' | t }}
        </button>
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="periode() === 'toutes'"
          (click)="periode.set('toutes'); charger(1)"
        >
          {{ 'Toutes' | t }}
        </button>
      </div>
      <label class="field" style="min-width:200px">
        <span class="sr-only">{{ 'Statut' | t }}</span>
        <select
          [value]="statut()"
          (change)="statut.set($any($event.target).value); charger(1)"
          [attr.aria-label]="'Filtrer par statut' | t"
        >
          <option value="">{{ 'Tous les statuts' | t }}</option>
          <option value="confirmee">{{ 'Confirmées' | t }}</option>
          <option value="annulee">{{ 'Annulées' | t }}</option>
          <option value="terminee">{{ 'Terminées' | t }}</option>
        </select>
      </label>
    </div>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (page(); as p) {
      @if (p.items.length === 0) {
        <app-etat-vide
          [titre]="'Aucune réservation' | t"
          [message]="'Aucune réservation ne correspond à ces filtres.' | t"
          icone="🎟"
        />
      } @else {
        <p class="muted">
          {{ (p.total === 1 ? '{n} réservation' : '{n} réservations') | t: { n: p.total } }}
        </p>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">{{ 'Séance' | t }}</th>
                <th scope="col">{{ 'Date' | t }}</th>
                <th scope="col">{{ 'Salle' | t }}</th>
                <th scope="col">{{ 'Sportif' | t }}</th>
                <th scope="col">{{ 'E-mail' | t }}</th>
                <th scope="col">{{ 'Statut' | t }}</th>
              </tr>
            </thead>
            <tbody>
              @for (r of p.items; track r.id) {
                <tr>
                  <td>
                    @if (r.seance) {
                      <a [routerLink]="['/gestionnaire/seances', r.seance.id, 'inscrits']">{{
                        r.seance.titre
                      }}</a>
                    }
                  </td>
                  <td>{{ r.seance?.dateDebut | dateL: 'EEE d MMM y · HH:mm' }}</td>
                  <td>{{ r.seance?.salle?.nom ?? '—' }}</td>
                  <td>{{ r.sportif?.prenom }} {{ r.sportif?.nom }}</td>
                  <td>
                    @if (r.sportif?.email) {
                      <a [href]="'mailto:' + r.sportif!.email">{{ r.sportif!.email }}</a>
                    }
                  </td>
                  <td>
                    <span class="badge badge--{{ r.statut }}">{{ libelle(r.statut) | t }}</span>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
        <app-pagination [page]="p.page" [pages]="p.pages" (changer)="charger($event)" />
      }
    }
  `,
})
export class ReservationsGestionnaireComponent {
  private readonly gestion = inject(GestionService);
  private readonly langue = inject(LangueService);
  readonly periode = signal<'a_venir' | 'toutes'>('a_venir');
  readonly statut = signal('');
  readonly page = signal<Page<Reservation> | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    this.charger(1);
  }

  libelle(statut: string): string {
    return STATUTS[statut] ?? statut;
  }

  charger(page: number): void {
    this.chargement.set(true);
    this.erreur.set('');
    this.gestion
      .reservationsGestionnaire({
        periode: this.periode(),
        statut: this.statut() || undefined,
        page,
      })
      .subscribe({
        next: (p) => {
          this.page.set(p);
          this.chargement.set(false);
        },
        error: (error) => {
          this.erreur.set(
            messageErreur(error, this.langue.t('Impossible de charger les réservations.')),
          );
          this.chargement.set(false);
        },
      });
  }
}
