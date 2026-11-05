import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Avis, Cible, Page } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import {
  EtatVideComponent,
  EtoilesComponent,
  LoaderComponent,
  PaginationComponent,
} from '../../../shared/ui';

type Filtre = 'non_moderes' | 'masques' | 'tous';

const ONGLETS: { code: Filtre; libelle: string }[] = [
  { code: 'non_moderes', libelle: 'Non modérés' },
  { code: 'masques', libelle: 'Masqués' },
  { code: 'tous', libelle: 'Tous' },
];

const TYPES: Record<string, string> = { salle: 'Salle', coach: 'Coach', seance: 'Séance' };
const LIENS: Record<string, string> = { salle: '/salles', coach: '/coachs', seance: '/seances' };

/** Modération des avis (US-A04, UC-21) : valider, masquer ou réafficher. */
@Component({
  selector: 'app-moderation',
  imports: [
    DatePipe,
    RouterLink,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    PaginationComponent,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Administration</span>
        <h1>Modération des avis</h1>
        <p>Un avis masqué disparaît des fiches et ne compte plus dans la note moyenne.</p>
      </div>
    </header>

    <div class="tabs" role="tablist" aria-label="Filtre des avis">
      @for (o of onglets; track o.code) {
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="filtre() === o.code"
          (click)="choisir(o.code)"
        >
          {{ o.libelle }}
        </button>
      }
    </div>

    @if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (!page()) {
      <app-loader />
    } @else if (page()!.items.length === 0) {
      <app-etat-vide
        [titre]="filtre() === 'non_moderes' ? 'Aucun avis en attente' : 'Aucun avis'"
        icone="✓"
        [message]="
          filtre() === 'non_moderes'
            ? 'Tous les avis publiés ont été modérés.'
            : 'Aucun avis ne correspond à ce filtre.'
        "
      />
    } @else {
      <ul class="reviews">
        @for (a of page()!.items; track a.id) {
          <li class="panel review">
            <div class="row row--between">
              <div>
                @if (a.cible; as c) {
                  <span class="card-kicker">{{ typeLibelle(c) }}</span>
                  <a class="review-target" [routerLink]="[lien(c), c.id]">{{
                    c.nom ?? 'Cible supprimée'
                  }}</a>
                }
                <p class="muted review-meta">
                  {{ a.auteur ?? 'Auteur inconnu' }} ·
                  <time [attr.datetime]="a.date">{{ a.date | date: 'd MMMM y, HH:mm' }}</time>
                </p>
              </div>
              <div class="chips">
                @if (a.estModere) {
                  <span class="badge badge--ok">Modéré</span>
                } @else {
                  <span class="badge badge--primary">En attente</span>
                }
                @if (!a.estVisible) {
                  <span class="badge badge--full">Masqué</span>
                }
              </div>
            </div>
            <app-etoiles [note]="a.note" [avecTexte]="false" />
            @if (a.commentaire) {
              <p class="review-text">{{ a.commentaire }}</p>
            } @else {
              <p class="muted">Pas de commentaire.</p>
            }
            <div class="row">
              @if (!a.estModere || !a.estVisible) {
                <button
                  type="button"
                  class="btn btn--sm"
                  [disabled]="enCours() === a.id"
                  (click)="moderer(a, 'valider')"
                >
                  Valider
                </button>
              }
              @if (a.estVisible) {
                <button
                  type="button"
                  class="btn btn--sm btn--danger"
                  [disabled]="enCours() === a.id"
                  (click)="moderer(a, 'masquer')"
                >
                  Masquer
                </button>
              } @else {
                <button
                  type="button"
                  class="btn btn--sm btn--ghost"
                  [disabled]="enCours() === a.id"
                  (click)="moderer(a, 'afficher')"
                >
                  Réafficher
                </button>
              }
            </div>
          </li>
        }
      </ul>
      <app-pagination [page]="page()!.page" [pages]="page()!.pages" (changer)="charger($event)" />
    }
  `,
  styles: `
    .reviews {
      display: grid;
      gap: 14px;
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .review {
      display: grid;
      gap: 10px;
    }
    .review-target {
      display: block;
      color: var(--ink);
      font-size: 17px;
      font-weight: 700;
      text-decoration: none;
    }
    .review-target:hover {
      color: var(--primary);
    }
    .review-meta {
      margin: 2px 0 0;
      font-size: 13px;
    }
    .review-text {
      margin: 0;
      color: var(--ink);
      white-space: pre-line;
      overflow-wrap: anywhere;
    }
  `,
})
export class ModerationComponent {
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly router = inject(Router);

  readonly onglets = ONGLETS;
  readonly filtre = signal<Filtre>('non_moderes');
  readonly page = signal<Page<Avis> | null>(null);
  readonly erreur = signal('');
  readonly enCours = signal<number | null>(null);

  constructor() {
    const demande = inject(ActivatedRoute).snapshot.queryParamMap.get('filtre');
    if (demande === 'masques' || demande === 'tous' || demande === 'non_moderes')
      this.filtre.set(demande);
    this.charger(1);
  }

  typeLibelle(c: Cible): string {
    return TYPES[c.type] ?? c.type;
  }

  lien(c: Cible): string {
    return LIENS[c.type] ?? '/';
  }

  choisir(filtre: Filtre): void {
    if (filtre === this.filtre()) return;
    this.filtre.set(filtre);
    void this.router.navigate([], { queryParams: { filtre }, replaceUrl: true });
    this.charger(1);
  }

  charger(numero: number): void {
    this.erreur.set('');
    this.page.set(null);
    const filtre = this.filtre() === 'tous' ? undefined : this.filtre();
    this.gestion.avisModeration({ filtre, page: numero }).subscribe({
      next: (p) => this.page.set(p),
      error: (e) => this.erreur.set(messageErreur(e, 'Impossible de charger les avis.')),
    });
  }

  moderer(a: Avis, action: 'valider' | 'masquer' | 'afficher'): void {
    this.enCours.set(a.id);
    this.gestion.moderer(a.id, action).subscribe({
      next: (maj) => {
        this.enCours.set(null);
        const garder = this.correspond(maj);
        this.page.update(
          (p) =>
            p && {
              ...p,
              total: garder ? p.total : p.total - 1,
              items: garder
                ? p.items.map((i) =>
                    i.id === maj.id ? { ...maj, cible: maj.cible ?? i.cible } : i,
                  )
                : p.items.filter((i) => i.id !== maj.id),
            },
        );
        this.toasts.succes(
          action === 'valider'
            ? 'Avis validé.'
            : action === 'masquer'
              ? 'Avis masqué.'
              : 'Avis réaffiché.',
        );
      },
      error: (e) => {
        this.enCours.set(null);
        this.toasts.erreur(messageErreur(e));
      },
    });
  }

  /** L'avis appartient-il encore à l'onglet affiché ? */
  private correspond(a: Avis): boolean {
    switch (this.filtre()) {
      case 'non_moderes':
        return !a.estModere && a.estVisible;
      case 'masques':
        return !a.estVisible;
      default:
        return true;
    }
  }
}
