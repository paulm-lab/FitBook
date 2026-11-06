import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
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
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    PaginationComponent,
    ...I18N,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Avis' | t }}</span>
        <h1>{{ (espace === 'gestionnaire' ? 'Avis sur mes salles' : 'Avis reçus') | t }}</h1>
        <p>{{ 'Les avis visibles publiés par les sportifs après leurs séances.' | t }}</p>
      </div>
      @if (moyenne(); as m) {
        <div class="stack" style="gap:4px;align-items:flex-end">
          <!-- Tous les avis tiennent sur la page : la moyenne porte sur le total. -->
          @if (m.nb === page()?.total) {
            <app-etoiles [note]="m.note" [nbAvis]="m.nb" />
          } @else {
            <app-etoiles [note]="m.note" />
            <small class="muted">{{ 'Moyenne des {n} avis affichés' | t: { n: m.nb } }}</small>
          }
        </div>
      }
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (page(); as p) {
      @if (p.items.length === 0) {
        <app-etat-vide
          [titre]="'Aucun avis pour le moment' | t"
          [message]="'Les avis apparaîtront ici après les premières séances terminées.' | t"
          icone="★"
        />
      } @else {
        <div class="stack">
          @for (a of p.items; track a.id) {
            <article class="panel">
              <div class="row row--between">
                <app-etoiles [note]="a.note" [avecTexte]="false" />
                <span class="muted">{{ a.date | dateL: 'd MMMM y' }}</span>
              </div>
              <!-- Commentaire affiché en texte : jamais interprété comme du HTML. -->
              @if (a.commentaire) {
                <p style="white-space:pre-line">{{ a.commentaire }}</p>
              }
              <p class="muted" style="margin:0">
                {{ a.auteur }}
                @if (a.cible; as c) {
                  · {{ 'sur' | t }} <a [routerLink]="lien(c)">{{ c.nom }}</a>
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
  private readonly langue = inject(LangueService);
  readonly espace = espaceCourant();
  readonly page = signal<Page<Avis> | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');
  /**
   * Moyenne des avis de la page affichée, avec leur nombre : l'API ne renvoie pas de moyenne
   * sur l'ensemble de ces avis (la note officielle de chaque cible est sur sa fiche publique).
   */
  readonly moyenne = computed<{ note: number; nb: number } | null>(() => {
    const items = this.page()?.items ?? [];
    if (items.length === 0) {
      return null;
    }
    const somme = items.reduce((s, a) => s + a.note, 0);
    return { note: Math.round((somme / items.length) * 10) / 10, nb: items.length };
  });

  constructor() {
    this.charger(1);
  }

  charger(page: number): void {
    this.chargement.set(true);
    const espace = this.espace === 'gestionnaire' ? 'gestionnaire' : 'coach';
    this.gestion.avisRecus(espace, page).subscribe({
      next: (p) => {
        this.page.set(p);
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(messageErreur(error, this.langue.t('Impossible de charger les avis.')));
        this.chargement.set(false);
      },
    });
  }

  lien(c: Cible): (string | number)[] {
    return [c.type === 'salle' ? '/salles' : c.type === 'coach' ? '/coachs' : '/seances', c.id];
  }
}
