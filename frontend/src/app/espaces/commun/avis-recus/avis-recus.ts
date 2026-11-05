import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Avis, Cible, Page } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import {
  EtatVideComponent,
  EtoilesComponent,
  LoaderComponent,
  PaginationComponent,
} from '../../../shared/ui';
import { espaceCourant } from '../espace';

/** Avis reçus par le coach ou par les salles du gestionnaire (US-C06, US-G06). */
@Component({
  selector: 'app-avis-recus',
  imports: [
    RouterLink,
    DatePipe,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    PaginationComponent,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Avis</span>
        <h1>{{ espace === 'gestionnaire' ? 'Avis sur mes salles' : 'Avis reçus' }}</h1>
        <p>Les avis visibles publiés par les sportifs après leurs séances.</p>
      </div>
      @if (moyenne() !== null) {
        <app-etoiles [note]="moyenne()" [nbAvis]="page()?.total ?? null" />
      }
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (page(); as p) {
      @if (p.items.length === 0) {
        <app-etat-vide
          titre="Aucun avis pour le moment"
          message="Les avis apparaîtront ici après les premières séances terminées."
          icone="★"
        />
      } @else {
        <div class="stack">
          @for (a of p.items; track a.id) {
            <article class="panel">
              <div class="row row--between">
                <app-etoiles [note]="a.note" [avecTexte]="false" />
                <span class="muted">{{ a.date | date: 'd MMMM y' }}</span>
              </div>
              <!-- Commentaire affiché en texte : jamais interprété comme du HTML. -->
              @if (a.commentaire) {
                <p style="white-space:pre-line">{{ a.commentaire }}</p>
              }
              <p class="muted" style="margin:0">
                {{ a.auteur }}
                @if (a.cible; as c) {
                  · sur <a [routerLink]="lien(c)">{{ c.nom }}</a>
                }
              </p>
            </article>
          }
        </div>
        <app-pagination [page]="p.page" [pages]="p.pages" (changer)="charger($event)" />
      }
    }
  `,
})
export class AvisRecusComponent {
  private readonly gestion = inject(GestionService);
  readonly espace = espaceCourant();
  readonly page = signal<Page<Avis> | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');
  readonly moyenne = signal<number | null>(null);

  constructor() {
    this.charger(1);
  }

  charger(page: number): void {
    this.chargement.set(true);
    const espace = this.espace === 'gestionnaire' ? 'gestionnaire' : 'coach';
    this.gestion.avisRecus(espace, page).subscribe({
      next: (p) => {
        this.page.set(p);
        // Moyenne de la page affichée (indicative) : la note officielle est sur la fiche publique.
        if (page === 1 && p.items.length) {
          this.moyenne.set(
            Math.round((p.items.reduce((s, a) => s + a.note, 0) / p.items.length) * 10) / 10,
          );
        }
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(messageErreur(error, 'Impossible de charger les avis.'));
        this.chargement.set(false);
      },
    });
  }

  lien(c: Cible): (string | number)[] {
    return [c.type === 'salle' ? '/salles' : c.type === 'coach' ? '/coachs' : '/seances', c.id];
  }
}
