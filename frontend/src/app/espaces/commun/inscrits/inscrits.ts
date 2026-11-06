import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
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
  imports: [RouterLink, LoaderComponent, EtatVideComponent, ...I18N],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Inscrits' | t }}</span>
        <h1>{{ donnees()?.seance?.titre ?? ('Séance' | t) }}</h1>
        @if (donnees(); as d) {
          <p>
            {{ d.seance.dateDebut | dateL: 'EEEE d MMMM y · HH:mm' }} –
            {{ d.seance.dateFin | dateL: 'HH:mm' }} ·
            {{ d.seance.salle?.nom ?? ('Sans salle' | t) }} ·
            <strong>{{
              '{pris} / {max} places prises' | t: { pris: d.nbConfirmes, max: d.seance.capaciteMax }
            }}</strong>
          </p>
        }
      </div>
      <div class="row no-print">
        <a class="btn btn--ghost" [routerLink]="'/' + espace + '/seances'">{{ '← Retour' | t }}</a>
        <button
          type="button"
          class="btn btn--ghost"
          (click)="exporter()"
          [disabled]="lignes().length === 0"
        >
          {{ 'Exporter en CSV' | t }}
        </button>
        <button type="button" class="btn btn--ghost" (click)="imprimer()">
          {{ 'Imprimer' | t }}
        </button>
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
        {{ 'Confirmés seulement' | t }}
      </label>
      @if (lignes().length === 0) {
        <app-etat-vide
          [titre]="'Aucun inscrit' | t"
          [message]="'Personne n’a encore réservé cette séance.' | t"
          icone="👥"
        />
      } @else {
        <div class="table-wrap">
          <table class="table">
            <caption class="sr-only">
              {{
                'Inscrits de la séance' | t
              }}
            </caption>
            <thead>
              <tr>
                <th scope="col">{{ 'Nom' | t }}</th>
                <th scope="col">{{ 'Prénom' | t }}</th>
                <th scope="col">{{ 'E-mail' | t }}</th>
                <th scope="col">{{ 'Téléphone' | t }}</th>
                <th scope="col">{{ 'Statut' | t }}</th>
                <th scope="col">{{ 'Réservé le' | t }}</th>
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
                    <span class="badge badge--{{ r.statut }}">{{ statut(r.statut) | t }}</span>
                  </td>
                  <td>{{ r.dateReservation | dateL: 'd/MM/y HH:mm' }}</td>
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
  private readonly langue = inject(LangueService);

  readonly espace = espaceCourant();
  readonly donnees = signal<Inscrits | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');
  readonly confirmesSeulement = signal(false);

  readonly lignes = computed<Reservation[]>(() => {
    const inscrits = this.donnees()?.inscrits ?? [];
    // Une séance passée a ses participants en « terminee » : ils restent des inscrits confirmés.
    return this.confirmesSeulement()
      ? inscrits.filter((r) => r.statut === 'confirmee' || r.statut === 'terminee')
      : inscrits;
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
          this.erreur.set(
            messageErreur(error, this.langue.t('Impossible de charger les inscrits.')),
          );
          this.chargement.set(false);
        },
      });
    });
  }

  statut(code: string): string {
    return STATUTS[code] ?? code;
  }

  /**
   * Export CSV (séparateur « ; » pour Excel en français, BOM UTF-8 pour les accents).
   * Une valeur commençant par = + - @, une tabulation ou un retour chariot est préfixée d'une
   * apostrophe : le tableur ne l'exécute pas comme une formule (injection CSV).
   */
  exporter(): void {
    const echapper = (v: string) => `"${v.replace(/"/g, '""')}"`;
    const cellule = (v: string | null | undefined) =>
      echapper(/^[=+\-@\t\r]/.test(v ?? '') ? `'${v}` : (v ?? ''));
    // Téléphone écrit ="…" : Excel le garde en texte (0 initial et +32 conservés).
    const telephone = (v: string | null | undefined) =>
      v && /^[+\d][\d\s./()-]*$/.test(v) ? echapper(`="${v}"`) : cellule(v);
    const lignes = [
      ['Nom', 'Prénom', 'E-mail', 'Téléphone', 'Statut']
        .map((v) => cellule(this.langue.t(v)))
        .join(';'),
      ...this.lignes().map((r) =>
        [
          r.sportif?.nom,
          r.sportif?.prenom,
          r.sportif?.email,
          r.sportif?.telephone,
          this.langue.t(this.statut(r.statut)),
        ]
          .map((v, i) => (i === 3 ? telephone(v) : cellule(v)))
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
