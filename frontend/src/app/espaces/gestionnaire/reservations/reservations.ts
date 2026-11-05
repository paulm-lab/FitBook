import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Page, Reservation } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { EtatVideComponent, LoaderComponent, PaginationComponent } from '../../../shared/ui';

const STATUTS: Record<string, string> = { confirmee: 'Confirmée', annulee: 'Annulée', terminee: 'Terminée' };

/** Réservations des séances de mes salles (US-G04). */
@Component({
  selector: 'app-reservations-gestionnaire',
  imports: [RouterLink, DatePipe, LoaderComponent, EtatVideComponent, PaginationComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Réservations</span>
        <h1>Réservations de mes salles</h1>
        <p>Préparez l'accueil des sportifs.</p>
      </div>
    </header>

    <div class="row" style="margin-bottom:16px">
      <div class="tabs" role="tablist" aria-label="Période" style="margin:0">
        <button type="button" class="tab" role="tab" [attr.aria-selected]="periode() === 'a_venir'" (click)="periode.set('a_venir'); charger(1)">À venir</button>
        <button type="button" class="tab" role="tab" [attr.aria-selected]="periode() === 'toutes'" (click)="periode.set('toutes'); charger(1)">Toutes</button>
      </div>
      <label class="field" style="min-width:200px">
        <span class="sr-only">Statut</span>
        <select [value]="statut()" (change)="statut.set($any($event.target).value); charger(1)" aria-label="Filtrer par statut">
          <option value="">Tous les statuts</option>
          <option value="confirmee">Confirmées</option>
          <option value="annulee">Annulées</option>
          <option value="terminee">Terminées</option>
        </select>
      </label>
    </div>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (page(); as p) {
      @if (p.items.length === 0) {
        <app-etat-vide titre="Aucune réservation" message="Aucune réservation ne correspond à ces filtres." icone="🎟" />
      } @else {
        <p class="muted">{{ p.total }} réservation(s)</p>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">Séance</th><th scope="col">Date</th><th scope="col">Salle</th>
                <th scope="col">Sportif</th><th scope="col">E-mail</th><th scope="col">Statut</th>
              </tr>
            </thead>
            <tbody>
              @for (r of p.items; track r.id) {
                <tr>
                  <td>@if (r.seance) { <a [routerLink]="['/gestionnaire/seances', r.seance.id, 'inscrits']">{{ r.seance.titre }}</a> }</td>
                  <td>{{ r.seance?.dateDebut | date: 'EEE d MMM y · HH:mm' }}</td>
                  <td>{{ r.seance?.salle?.nom ?? '—' }}</td>
                  <td>{{ r.sportif?.prenom }} {{ r.sportif?.nom }}</td>
                  <td>@if (r.sportif?.email) { <a [href]="'mailto:' + r.sportif!.email">{{ r.sportif!.email }}</a> }</td>
                  <td><span class="badge badge--{{ r.statut }}">{{ libelle(r.statut) }}</span></td>
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
    this.gestion.reservationsGestionnaire({ periode: this.periode(), statut: this.statut() || undefined, page }).subscribe({
      next: p => {
        this.page.set(p);
        this.chargement.set(false);
      },
      error: error => {
        this.erreur.set(messageErreur(error, 'Impossible de charger les réservations.'));
        this.chargement.set(false);
      },
    });
  }
}
