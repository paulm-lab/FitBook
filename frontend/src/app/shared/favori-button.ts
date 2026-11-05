import { Component, Injectable, computed, effect, inject, input, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService } from '../core/auth/auth.service';
import { messageErreur } from '../core/http';
import { Favori, TypeCible } from '../core/models';
import { CompteService } from '../core/services/compte.service';
import { ToastService } from '../core/services/toast.service';

/** Favoris de l'utilisateur connecté, chargés une fois et partagés par tous les boutons. */
@Injectable({ providedIn: 'root' })
export class FavorisStore {
  private readonly compte = inject(CompteService);
  private readonly auth = inject(AuthService);
  readonly favoris = signal<Favori[]>([]);

  constructor() {
    // Rechargés à chaque connexion, vidés à la déconnexion.
    effect(() => {
      if (this.auth.estConnecte()) {
        this.recharger();
      } else {
        this.favoris.set([]);
      }
    });
  }

  recharger(): void {
    this.compte
      .favoris()
      .subscribe({
        next: (favoris) => this.favoris.set(favoris),
        error: () => this.favoris.set([]),
      });
  }

  trouver(type: TypeCible, id: number): Favori | undefined {
    return this.favoris().find((f) => f.type === type && f.cible?.id === id);
  }
}

/** Bouton cœur : ajoute ou retire une salle, un coach ou une séance des favoris (US-S05). */
@Component({
  selector: 'app-favori',
  template: `
    <button
      type="button"
      class="fav-btn"
      [class.on]="favori()"
      [disabled]="envoi()"
      [attr.aria-pressed]="!!favori()"
      [attr.aria-label]="favori() ? 'Retirer des favoris' : 'Ajouter aux favoris'"
      [title]="favori() ? 'Retirer des favoris' : 'Ajouter aux favoris'"
      (click)="basculer($event)"
    >
      <span aria-hidden="true">{{ favori() ? '♥' : '♡' }}</span>
      @if (avecTexte()) {
        <span>{{ favori() ? 'Favori' : 'Ajouter aux favoris' }}</span>
      }
    </button>
  `,
})
export class FavoriButtonComponent {
  readonly type = input.required<TypeCible>();
  readonly cibleId = input.required<number>();
  readonly avecTexte = input(false);

  private readonly store = inject(FavorisStore);
  private readonly compte = inject(CompteService);
  private readonly auth = inject(AuthService);
  private readonly toasts = inject(ToastService);
  private readonly router = inject(Router);

  readonly envoi = signal(false);
  readonly favori = computed(() =>
    this.store.favoris().find((f) => f.type === this.type() && f.cible?.id === this.cibleId()),
  );

  basculer(event: Event): void {
    // Le bouton est souvent posé sur une carte cliquable : on ne suit pas le lien.
    event.preventDefault();
    event.stopPropagation();
    if (!this.auth.estConnecte()) {
      void this.router.navigate(['/connexion'], { queryParams: { returnUrl: this.router.url } });
      return;
    }

    this.envoi.set(true);
    const existant = this.favori();
    const requete: Observable<unknown> = existant
      ? this.compte.retirerFavori(existant.id)
      : this.compte.ajouterFavori(this.type(), this.cibleId());
    requete.subscribe({
      next: () => {
        this.store.recharger();
        this.toasts.succes(existant ? 'Retiré de vos favoris.' : 'Ajouté à vos favoris.');
        this.envoi.set(false);
      },
      error: (error: unknown) => {
        this.toasts.erreur(messageErreur(error));
        this.envoi.set(false);
      },
    });
  }
}
