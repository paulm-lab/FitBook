import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { erreursChamps, messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { Avis, TypeCible } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { ToastService } from '../../../core/services/toast.service';
import {
  ConfirmService,
  EtatVideComponent,
  EtoilesComponent,
  LoaderComponent,
  SaisieNoteComponent,
} from '../../../shared/ui';

const CHEMINS: Record<TypeCible, string> = {
  salle: '/salles',
  coach: '/coachs',
  seance: '/seances',
};
const TYPES: Record<TypeCible, string> = { salle: 'Salle', coach: 'Coach', seance: 'Séance' };

/**
 * Mes avis (US-S06) : modification en place et suppression.
 * Le commentaire est affiché en texte ({{ }}), jamais interprété comme du HTML.
 */
@Component({
  selector: 'app-mes-avis',
  imports: [
    RouterLink,
    FormsModule,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    SaisieNoteComponent,
    ...I18N,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Mon espace' | t }}</span>
        <h1>{{ 'Mes avis' | t }}</h1>
        <p>
          {{
            'Un avis par salle, coach ou séance. Une modification repasse par la modération.' | t
          }}
        </p>
      </div>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (avis().length === 0) {
      <app-etat-vide
        [titre]="textes.aucunAvis | t"
        [message]="
          'Après une séance terminée, partagez votre expérience depuis la fiche de la salle, du coach ou de la séance.'
            | t
        "
        icone="★"
      />
    } @else {
      <ul class="stack review-list">
        @for (a of avis(); track a.id) {
          <li class="panel">
            <div class="row row--between">
              <div>
                <span class="card-kicker">{{ types[a.cible?.type ?? 'seance'] | t }}</span>
                <h2>
                  @if (a.cible) {
                    <a [routerLink]="[chemins[a.cible.type], a.cible.id]">{{ a.cible.nom }}</a>
                  } @else {
                    {{ 'Élément supprimé' | t }}
                  }
                </h2>
              </div>
              @if (!a.estVisible) {
                <span class="badge badge--annulee" [title]="textes.masque | t">{{
                  'Masqué' | t
                }}</span>
              } @else if (a.estModere) {
                <span class="badge badge--confirmee">{{ 'Publié · validé' | t }}</span>
              } @else {
                <span class="badge">{{ 'Publié' | t }}</span>
              }
            </div>

            @if (edition() === a.id) {
              <form class="form" (ngSubmit)="enregistrer(a)">
                <app-saisie-note [(valeur)]="note" [nom]="'note-' + a.id" />
                @if (erreurs()['note']) {
                  <small class="field-error" role="alert">{{ erreurs()['note'] }}</small>
                }
                <label class="field">
                  <span>{{ 'Commentaire (facultatif)' | t }}</span>
                  <textarea
                    name="commentaire"
                    [(ngModel)]="commentaire"
                    maxlength="1000"
                    [attr.aria-invalid]="!!erreurs()['commentaire']"
                  ></textarea>
                  @if (erreurs()['commentaire']) {
                    <small class="field-error">{{ erreurs()['commentaire'] }}</small>
                  }
                </label>
                <div class="row">
                  <button type="submit" class="btn btn--sm" [disabled]="envoi() || note < 1">
                    {{ 'Enregistrer' | t }}
                  </button>
                  <button type="button" class="btn btn--sm btn--ghost" (click)="edition.set(null)">
                    {{ 'Annuler' | t }}
                  </button>
                </div>
              </form>
            } @else {
              <app-etoiles [note]="a.note" [avecTexte]="false" />
              @if (a.commentaire) {
                <p class="comment">{{ a.commentaire }}</p>
              }
              <div class="row row--between">
                <span class="muted">{{
                  'Le {date}' | t: { date: a.date | dateL: 'd MMMM y' }
                }}</span>
                <div class="row">
                  <button type="button" class="btn btn--sm btn--ghost" (click)="modifier(a)">
                    {{ 'Modifier' | t }}
                  </button>
                  <button type="button" class="btn btn--sm btn--ghost" (click)="supprimer(a)">
                    {{ 'Supprimer' | t }}
                  </button>
                </div>
              </div>
            }
          </li>
        }
      </ul>
    }
  `,
  styles: `
    .review-list {
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .panel h2 {
      margin: 4px 0 8px;
      font-size: 19px;
    }
    .panel h2 a {
      color: var(--ink);
      text-decoration: none;
    }
    .panel h2 a:hover {
      color: var(--primary);
    }
    .comment {
      margin: 8px 0;
      color: var(--text);
      white-space: pre-line;
      overflow-wrap: anywhere;
    }
  `,
})
export class MesAvisComponent {
  private readonly compte = inject(CompteService);
  private readonly confirm = inject(ConfirmService);
  private readonly toasts = inject(ToastService);
  private readonly langue = inject(LangueService);

  readonly chemins = CHEMINS;
  /** Textes contenant une apostrophe, impossibles à écrire dans un attribut lié du gabarit. */
  readonly textes = {
    aucunAvis: "Vous n'avez pas encore donné d'avis",
    masque: "Masqué par la modération : il n'apparaît plus sur la fiche",
  };
  readonly types = TYPES;
  readonly avis = signal<Avis[]>([]);
  readonly chargement = signal(true);
  readonly edition = signal<number | null>(null);
  readonly envoi = signal(false);
  readonly erreurs = signal<Record<string, string>>({});
  note = 0;
  commentaire = '';

  constructor() {
    this.charger();
  }

  modifier(avis: Avis): void {
    this.note = avis.note;
    this.commentaire = avis.commentaire ?? '';
    this.erreurs.set({});
    this.edition.set(avis.id);
  }

  enregistrer(avis: Avis): void {
    this.envoi.set(true);
    this.compte.modifierAvis(avis.id, this.note, this.commentaire.trim()).subscribe({
      next: (modifie) => {
        this.avis.update((liste) => liste.map((a) => (a.id === modifie.id ? modifie : a)));
        this.edition.set(null);
        this.envoi.set(false);
        this.toasts.succes(this.langue.t('Avis modifié.'));
      },
      error: (error: unknown) => {
        this.erreurs.set(erreursChamps(error));
        this.envoi.set(false);
        this.toasts.erreur(messageErreur(error, this.langue.t('L’avis n’a pas pu être modifié.')));
      },
    });
  }

  async supprimer(avis: Avis): Promise<void> {
    const ok = await this.confirm.demander({
      titre: this.langue.t('Supprimer cet avis ?'),
      message: this.langue.t(
        'Il disparaîtra de la fiche et ne comptera plus dans la note moyenne.',
      ),
      confirmer: this.langue.t('Supprimer'),
      danger: true,
    });
    if (!ok) return;
    this.compte.supprimerAvis(avis.id).subscribe({
      next: () => {
        this.avis.update((liste) => liste.filter((a) => a.id !== avis.id));
        this.toasts.succes(this.langue.t('Avis supprimé.'));
      },
      error: (error: unknown) =>
        this.toasts.erreur(messageErreur(error, this.langue.t('L’avis n’a pas pu être supprimé.'))),
    });
  }

  private charger(): void {
    this.compte.mesAvis().subscribe({
      next: (avis) => {
        this.avis.set(avis);
        this.chargement.set(false);
      },
      error: (error: unknown) => {
        this.toasts.erreur(
          messageErreur(error, this.langue.t('Vos avis n’ont pas pu être chargés.')),
        );
        this.chargement.set(false);
      },
    });
  }
}
