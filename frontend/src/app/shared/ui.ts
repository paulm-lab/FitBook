import { DecimalPipe } from '@angular/common';
import { Component, Directive, ElementRef, HostListener, Injectable, computed, inject, input, model, output, signal } from '@angular/core';
import { ToastService } from '../core/services/toast.service';

/*
 * Petits composants d'interface partagés par toutes les pages.
 * Leurs styles sont dans src/styles.css (jetons de la charte graphique).
 */

/** Donne le focus à l'élément dès son affichage (fenêtres modales). */
@Directive({ selector: '[appAutoFocus]' })
export class AutoFocusDirective {
  constructor() {
    const element = inject(ElementRef<HTMLElement>);
    setTimeout(() => element.nativeElement.focus(), 0);
  }
}

/** Image de secours si la photo distante ne se charge pas. */
@Directive({ selector: 'img[appSecours]' })
export class ImageSecoursDirective {
  private readonly element = inject(ElementRef<HTMLImageElement>);

  @HostListener('error')
  remplacer(): void {
    const image = this.element.nativeElement;
    if (image.dataset['secours']) return;
    image.dataset['secours'] = 'true';
    image.src = '/assets/fitbook-media-fallback.svg';
  }
}

/** Pile des toasts, annoncés aux lecteurs d'écran (aria-live). */
@Component({
  selector: 'app-toasts',
  template: `
    <div class="toasts" aria-live="polite" aria-atomic="false">
      @for (toast of toasts.toasts(); track toast.id) {
        <div class="toast toast--{{ toast.type }}" [attr.role]="toast.type === 'erreur' ? 'alert' : 'status'">
          <span>{{ toast.message }}</span>
          <button type="button" class="toast-close" (click)="toasts.fermer(toast.id)" aria-label="Fermer le message">×</button>
        </div>
      }
    </div>
  `,
})
export class ToastsComponent {
  readonly toasts = inject(ToastService);
}

export interface DemandeConfirmation {
  titre: string;
  message: string;
  confirmer?: string;
  danger?: boolean;
}

/** Fenêtre de confirmation avant une action irréversible (annulation, suppression). */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  readonly demande = signal<(DemandeConfirmation & { repondre: (ok: boolean) => void }) | null>(null);

  demander(demande: DemandeConfirmation): Promise<boolean> {
    return new Promise(resolve => {
      this.demande.set({
        ...demande,
        repondre: ok => {
          this.demande.set(null);
          resolve(ok);
        },
      });
    });
  }
}

@Component({
  selector: 'app-confirm-dialog',
  template: `
    @if (confirm.demande(); as d) {
      <div class="modal-backdrop" (click)="d.repondre(false)"></div>
      <div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">
        <h2 id="confirm-title">{{ d.titre }}</h2>
        <p id="confirm-message">{{ d.message }}</p>
        <div class="modal-actions">
          <button type="button" class="btn btn--ghost" (click)="d.repondre(false)">Retour</button>
          <button type="button" class="btn" [class.btn--danger]="d.danger" (click)="d.repondre(true)" appAutoFocus>
            {{ d.confirmer ?? 'Confirmer' }}
          </button>
        </div>
      </div>
    }
  `,
  imports: [AutoFocusDirective],
})
export class ConfirmDialogComponent {
  readonly confirm = inject(ConfirmService);

  @HostListener('document:keydown.escape')
  fermer(): void {
    this.confirm.demande()?.repondre(false);
  }
}

@Component({
  selector: 'app-loader',
  template: `<div class="loader" role="status"><span class="loader-spinner" aria-hidden="true"></span><span>{{ texte() }}</span></div>`,
})
export class LoaderComponent {
  readonly texte = input('Chargement…');
}

@Component({
  selector: 'app-etat-vide',
  template: `
    <div class="empty-state">
      <span class="empty-state-icon" aria-hidden="true">{{ icone() }}</span>
      <h3>{{ titre() }}</h3>
      @if (message()) { <p>{{ message() }}</p> }
      <ng-content />
    </div>
  `,
})
export class EtatVideComponent {
  readonly titre = input.required<string>();
  readonly message = input('');
  readonly icone = input('○');
}

/** Pagination accessible : précédent, numéros de page proches, suivant. */
@Component({
  selector: 'app-pagination',
  template: `
    @if (pages() > 1) {
      <nav class="pagination" aria-label="Pagination">
        <button type="button" class="pagination-btn" [disabled]="page() <= 1" (click)="aller(page() - 1)">← Précédent</button>
        @for (p of numeros(); track p) {
          <button type="button" class="pagination-btn" [class.active]="p === page()" [attr.aria-current]="p === page() ? 'page' : null" (click)="aller(p)">{{ p }}</button>
        }
        <button type="button" class="pagination-btn" [disabled]="page() >= pages()" (click)="aller(page() + 1)">Suivant →</button>
      </nav>
    }
  `,
})
export class PaginationComponent {
  readonly page = input.required<number>();
  readonly pages = input.required<number>();
  readonly changer = output<number>();

  readonly numeros = computed(() => {
    const debut = Math.max(1, Math.min(this.page() - 2, this.pages() - 4));
    return Array.from({ length: Math.min(5, this.pages()) }, (_, i) => debut + i);
  });

  aller(page: number): void {
    if (page >= 1 && page <= this.pages() && page !== this.page()) {
      this.changer.emit(page);
    }
  }
}

/** Note moyenne en étoiles (lecture seule). */
@Component({
  selector: 'app-etoiles',
  template: `
    <span class="stars" [attr.aria-label]="libelle()">
      <span class="stars-value" aria-hidden="true">
        @for (i of [1, 2, 3, 4, 5]; track i) {
          <span [class.on]="i <= arrondi()">★</span>
        }
      </span>
      @if (avecTexte()) {
        <span class="stars-text">{{ note() !== null ? (note()! | number: '1.1-1') : 'Pas encore d’avis' }}@if (nbAvis() !== null && note() !== null) { · {{ nbAvis() }} avis }</span>
      }
    </span>
  `,
  imports: [DecimalPipe],
})
export class EtoilesComponent {
  readonly note = input<number | null | undefined>(null);
  readonly nbAvis = input<number | null>(null);
  readonly avecTexte = input(true);
  readonly arrondi = computed(() => Math.round(this.note() ?? 0));
  readonly libelle = computed(() => (this.note() != null ? `Note moyenne ${this.note()} sur 5` : 'Pas encore noté'));
}

/** Saisie d'une note de 1 à 5 (boutons radio stylés en étoiles, utilisables au clavier). */
@Component({
  selector: 'app-saisie-note',
  template: `
    <fieldset class="star-input">
      <legend>{{ legende() }}</legend>
      @for (i of [1, 2, 3, 4, 5]; track i) {
        <label [class.on]="i <= (survol() || valeur())">
          <input type="radio" [name]="nom()" [value]="i" [checked]="valeur() === i" (change)="valeur.set(i)">
          <span (mouseenter)="survol.set(i)" (mouseleave)="survol.set(0)" aria-hidden="true">★</span>
          <span class="sr-only">{{ i }} étoile{{ i > 1 ? 's' : '' }}</span>
        </label>
      }
    </fieldset>
  `,
})
export class SaisieNoteComponent {
  readonly valeur = model(0);
  readonly legende = input('Votre note');
  readonly nom = input('note');
  readonly survol = signal(0);
}
