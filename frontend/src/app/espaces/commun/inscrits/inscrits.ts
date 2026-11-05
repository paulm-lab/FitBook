import { DatePipe } from '@angular/common';
import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Reservation } from '../../../core/models';
import { GestionService, Inscrits } from '../../../core/services/gestion.service';
import { EtatVideComponent, LoaderComponent } from '../../../shared/ui';
import { espaceCourant } from '../espace';

const STATUTS: Record<string, string> = {
  confirmee: 'Confirmée',
  annulee: 'Annulée',
  terminee: 'Terminée',
};

/** Liste des inscrits d'une séance (US-C05, US-G04), export CSV et impression. */
@Component({
  selector: 'app-inscrits',
  imports: [RouterLink, DatePipe, LoaderComponent, EtatVideComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Inscrits</span>
        <h1>{{ donnees()?.seance?.titre ?? 'Séance' }}</h1>
        @if (donnees(); as d) {
          <p>
            {{ d.seance.dateDebut | date: 'EEEE d MMMM y · HH:mm' }} –
            {{ d.seance.dateFin | date: 'HH:mm' }} · {{ d.seance.salle?.nom ?? 'Sans salle' }} ·
            <strong>{{ d.nbConfirmes }} / {{ d.seance.capaciteMax }}</strong> places prises
          </p>
        }
      </div>
      <div class="row no-print">
        <a class="btn btn--ghost" [routerLink]="'/' + espace + '/seances'">← Retour</a>
        <button
          type="button"
          class="btn btn--ghost"
          (click)="exporter()"
          [disabled]="lignes().length === 0"
        >
          Exporter en CSV
        </button>
        <button type="button" class="btn btn--ghost" (click)="imprimer()">Imprimer</button>
      </div>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else {
      <label class="check no-print" style="margin-bottom:16px">
        <input
          type="checkbox"
          [checked]="confirmesSeulement()"
          (change)="confirmesSeulement.set($any($event.target).checked)"
        />
        Confirmés seulement
      </label>
      @if (lignes().length === 0) {
        <app-etat-vide
          titre="Aucun inscrit"
          message="Personne n'a encore réservé cette séance."
          icone="👥"
        />
      } @else {
        <div class="table-wrap">
          <table class="table">
            <caption class="sr-only">
              Inscrits de la séance
            </caption>
            <thead>
              <tr>
                <th scope="col">Nom</th>
                <th scope="col">Prénom</th>
                <th scope="col">E-mail</th>
                <th scope="col">Téléphone</th>
                <th scope="col">Statut</th>
                <th scope="col">Réservé le</th>
              </tr>
            </thead>
            <tbody>
              @for (r of lignes(); track r.id) {
                <tr>
                  <td>{{ r.sportif?.nom }}</td>
                  <td>{{ r.sportif?.prenom }}</td>
                  <td>
                    @if (r.sportif?.email) {
                      <a [href]="'mailto:' + r.sportif!.email">{{ r.sportif!.email }}</a>
                    }
                  </td>
                  <td>{{ r.sportif?.telephone ?? '—' }}</td>
                  <td>
                    <span class="badge badge--{{ r.statut }}">{{ statut(r.statut) }}</span>
                  </td>
                  <td>{{ r.dateReservation | date: 'd/MM/y HH:mm' }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    }
  `,
})
export class InscritsComponent {
  readonly id = input.required<string>();
  private readonly gestion = inject(GestionService);

  readonly espace = espaceCourant();
  readonly donnees = signal<Inscrits | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');
  readonly confirmesSeulement = signal(false);

  readonly lignes = computed<Reservation[]>(() => {
    const inscrits = this.donnees()?.inscrits ?? [];
    return this.confirmesSeulement() ? inscrits.filter((r) => r.statut === 'confirmee') : inscrits;
  });

  constructor() {
    effect(() => {
      const id = Number(this.id());
      this.chargement.set(true);
      this.gestion.inscrits(id).subscribe({
        next: (d) => {
          this.donnees.set(d);
          this.chargement.set(false);
        },
        error: (error) => {
          this.erreur.set(messageErreur(error, 'Impossible de charger les inscrits.'));
          this.chargement.set(false);
        },
      });
    });
  }

  statut(code: string): string {
    return STATUTS[code] ?? code;
  }

  /** Export CSV (séparateur « ; » pour Excel en français, BOM UTF-8 pour les accents). */
  exporter(): void {
    const cellule = (v: string | null | undefined) => `"${(v ?? '').replace(/"/g, '""')}"`;
    const lignes = [
      ['Nom', 'Prénom', 'E-mail', 'Téléphone', 'Statut'].map(cellule).join(';'),
      ...this.lignes().map((r) =>
        [
          r.sportif?.nom,
          r.sportif?.prenom,
          r.sportif?.email,
          r.sportif?.telephone,
          this.statut(r.statut),
        ]
          .map(cellule)
          .join(';'),
      ),
    ];
    const blob = new Blob(['﻿' + lignes.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const lien = document.createElement('a');
    lien.href = url;
    lien.download = `inscrits-seance-${this.id()}.csv`;
    lien.click();
    URL.revokeObjectURL(url);
  }

  imprimer(): void {
    window.print();
  }
}
