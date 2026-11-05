import { DatePipe } from '@angular/common';
import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Favori, TypeCible } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { ToastService } from '../../../core/services/toast.service';
import { FavorisStore } from '../../../shared/favori-button';
import { EtatVideComponent, ImageSecoursDirective } from '../../../shared/ui';

const GROUPES: { type: TypeCible; titre: string; chemin: string }[] = [
  { type: 'salle', titre: 'Salles', chemin: '/salles' },
  { type: 'coach', titre: 'Coachs', chemin: '/coachs' },
  { type: 'seance', titre: 'Séances', chemin: '/seances' },
];

/** Mes favoris regroupés par type (US-S05). Source : FavorisStore, partagé avec les boutons cœur. */
@Component({
  selector: 'app-mes-favoris',
  imports: [RouterLink, DatePipe, EtatVideComponent, ImageSecoursDirective],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Mon espace</span>
        <h1>Mes favoris</h1>
        <p>Les salles, coachs et séances que vous avez gardés sous la main.</p>
      </div>
    </header>

    @if (store.favoris().length === 0) {
      <app-etat-vide titre="Aucun favori pour le moment" message="Touchez le cœur ♡ sur une salle, un coach ou une séance pour l'ajouter ici." icone="♡">
        <a class="btn" routerLink="/recherche">Explorer FitBook</a>
      </app-etat-vide>
    } @else {
      @for (groupe of groupes(); track groupe.type) {
        <section class="fav-group" [attr.aria-labelledby]="'fav-' + groupe.type">
          <h2 [id]="'fav-' + groupe.type">{{ groupe.titre }} <span class="badge">{{ groupe.items.length }}</span></h2>
          @if (groupe.items.length === 0) {
            <p class="muted">Aucun favori dans cette catégorie.</p>
          } @else {
            <ul class="grid fav-list">
              @for (f of groupe.items; track f.id) {
                <li class="card">
                  <a class="card-media" [routerLink]="[groupe.chemin, f.cible?.id]" tabindex="-1" aria-hidden="true">
                    <img [src]="f.cible?.image || '/assets/fitbook-media-fallback.svg'" alt="" loading="lazy" appSecours>
                  </a>
                  <div class="card-body">
                    <h3 class="card-title"><a [routerLink]="[groupe.chemin, f.cible?.id]">{{ f.cible?.nom ?? 'Élément supprimé' }}</a></h3>
                    @if (f.cible?.dateDebut) { <p class="card-meta">{{ f.cible?.dateDebut | date: 'EEEE d MMMM · HH:mm' }}</p> }
                    <p class="card-meta muted">Ajouté le {{ f.date | date: 'd MMM y' }}</p>
                    <div class="card-footer">
                      <a class="btn btn--sm btn--ghost" [routerLink]="[groupe.chemin, f.cible?.id]">Voir la fiche</a>
                      <button type="button" class="btn btn--sm btn--ghost" [disabled]="retrait() === f.id" (click)="retirer(f)"
                        [attr.aria-label]="'Retirer ' + (f.cible?.nom ?? 'ce favori') + ' des favoris'">♥ Retirer</button>
                    </div>
                  </div>
                </li>
              }
            </ul>
          }
        </section>
      }
    }
  `,
  styles: `
    .fav-group { margin-bottom: 32px; }
    .fav-group h2 { display: flex; align-items: center; gap: 10px; font-size: 21px; }
    .fav-list { margin: 0; padding: 0; list-style: none; }
  `,
})
export class MesFavorisComponent {
  readonly store = inject(FavorisStore);
  private readonly compte = inject(CompteService);
  private readonly toasts = inject(ToastService);

  readonly retrait = signal<number | null>(null);
  readonly groupes = computed(() =>
    GROUPES.map(g => ({ ...g, items: this.store.favoris().filter((f: Favori) => f.type === g.type) })),
  );

  constructor() {
    this.store.recharger();
  }

  retirer(favori: Favori): void {
    this.retrait.set(favori.id);
    this.compte.retirerFavori(favori.id).subscribe({
      next: () => {
        this.store.recharger();
        this.toasts.succes('Retiré de vos favoris.');
        this.retrait.set(null);
      },
      error: (error: unknown) => {
        this.toasts.erreur(messageErreur(error));
        this.retrait.set(null);
      },
    });
  }
}
