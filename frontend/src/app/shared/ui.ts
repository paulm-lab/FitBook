import {
  Component,
  Directive,
  ElementRef,
  Injectable,
  computed,
  inject,
  input,
  model,
  output,
  signal,
} from '@angular/core';
import { LangueService } from '../core/i18n/langue.service';
import { I18N } from '../core/i18n/pipes';
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
@Directive({ selector: 'img[appSecours]', host: { '(error)': 'remplacer()' } })
export class ImageSecoursDirective {
  private readonly element = inject(ElementRef<HTMLImageElement>);

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
        <div
          class="toast toast--{{ toast.type }}"
          [attr.role]="toast.type === 'erreur' ? 'alert' : 'status'"
        >
          <span>{{ toast.message | t }}</span>
          <button
            type="button"
            class="toast-close"
            (click)="toasts.fermer(toast.id)"
            [attr.aria-label]="'Fermer le message' | t"
          >
            ×
          </button>
        </div>
      }
    </div>
  `,
  imports: [...I18N],
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
  readonly demande = signal<(DemandeConfirmation & { repondre: (ok: boolean) => void }) | null>(
    null,
  );

  demander(demande: DemandeConfirmation): Promise<boolean> {
    return new Promise((resolve) => {
      this.demande.set({
        ...demande,
        repondre: (ok) => {
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
      <!-- Fond cliquable à la souris ; au clavier, Échap ferme la fenêtre (host ci-dessous). -->
      <!-- eslint-disable-next-line @angular-eslint/template/click-events-have-key-events, @angular-eslint/template/interactive-supports-focus -->
      <div class="modal-backdrop" (click)="d.repondre(false)"></div>
      <div
        class="modal"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="confirm-title"
        aria-describedby="confirm-message"
      >
        <h2 id="confirm-title">{{ d.titre | t }}</h2>
        <p id="confirm-message">{{ d.message | t }}</p>
        <div class="modal-actions">
          <button type="button" class="btn btn--ghost" (click)="d.repondre(false)">
            {{ 'Retour' | t }}
          </button>
          <button
            type="button"
            class="btn"
            [class.btn--danger]="d.danger"
            (click)="d.repondre(true)"
            appAutoFocus
          >
            {{ d.confirmer ?? 'Confirmer' | t }}
          </button>
        </div>
      </div>
    }
  `,
  imports: [AutoFocusDirective, ...I18N],
  // Échap ferme la fenêtre.
  host: { '(document:keydown.escape)': 'fermer()' },
})
export class ConfirmDialogComponent {
  readonly confirm = inject(ConfirmService);

  fermer(): void {
    this.confirm.demande()?.repondre(false);
  }
}

@Component({
  selector: 'app-loader',
  template: `<div class="loader" role="status">
    <span class="loader-spinner" aria-hidden="true"></span><span>{{ texte() | t }}</span>
  </div>`,
  imports: [...I18N],
})
export class LoaderComponent {
  readonly texte = input('Chargement…');
}

@Component({
  selector: 'app-etat-vide',
  template: `
    <div class="empty-state">
      <span class="empty-state-icon" aria-hidden="true">{{ icone() }}</span>
      <h3>{{ titre() | t }}</h3>
      @if (message()) {
        <p>{{ message() | t }}</p>
      }
      <ng-content />
    </div>
  `,
  imports: [...I18N],
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
      <nav class="pagination" [attr.aria-label]="'Pagination' | t">
        <button
          type="button"
          class="pagination-btn"
          [disabled]="page() <= 1"
          (click)="aller(page() - 1)"
        >
          {{ '← Précédent' | t }}
        </button>
        @for (p of numeros(); track p) {
          <button
            type="button"
            class="pagination-btn"
            [class.active]="p === page()"
            [attr.aria-current]="p === page() ? 'page' : null"
            (click)="aller(p)"
          >
            {{ p }}
          </button>
        }
        <button
          type="button"
          class="pagination-btn"
          [disabled]="page() >= pages()"
          (click)="aller(page() + 1)"
        >
          {{ 'Suivant →' | t }}
        </button>
      </nav>
    }
  `,
  imports: [...I18N],
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
        <span class="stars-text"
          >{{ note() !== null ? (note()! | nombreL: '1.1-1') : ('Pas encore d’avis' | t) }}
          @if (nbAvis() !== null && note() !== null) {
            · {{ (nbAvis() === 1 ? '1 avis' : '{n} avis') | t: { n: nbAvis() } }}
          }
        </span>
      }
    </span>
  `,
  imports: [...I18N],
})
export class EtoilesComponent {
  private readonly langue = inject(LangueService);
  readonly note = input<number | null | undefined>(null);
  readonly nbAvis = input<number | null>(null);
  readonly avecTexte = input(true);
  readonly arrondi = computed(() => Math.round(this.note() ?? 0));
  readonly libelle = computed(() =>
    this.note() != null
      ? this.langue.t('Note moyenne {note} sur 5', { note: this.note() })
      : this.langue.t('Pas encore noté'),
  );
}

/** Saisie d'une note de 1 à 5 (boutons radio stylés en étoiles, utilisables au clavier). */
@Component({
  selector: 'app-saisie-note',
  template: `
    <fieldset class="star-input">
      <legend>{{ legende() | t }}</legend>
      @for (i of [1, 2, 3, 4, 5]; track i) {
        <label [class.on]="i <= (survol() || valeur())">
          <input
            type="radio"
            [name]="nom()"
            [value]="i"
            [checked]="valeur() === i"
            (change)="valeur.set(i)"
          />
          <span (mouseenter)="survol.set(i)" (mouseleave)="survol.set(0)" aria-hidden="true"
            >★</span
          >
          <span class="sr-only">{{ (i > 1 ? '{n} étoiles' : '{n} étoile') | t: { n: i } }}</span>
        </label>
      }
    </fieldset>
  `,
  imports: [...I18N],
})
export class SaisieNoteComponent {
  readonly valeur = model(0);
  readonly legende = input('Votre note');
  readonly nom = input('note');
  readonly survol = signal(0);
}
