import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, input, output, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';
import { AuthService } from '../core/auth/auth.service';
import { messageErreur } from '../core/http';
import { Avis, Note, TypeCible } from '../core/models';
import { CompteService } from '../core/services/compte.service';
import { ToastService } from '../core/services/toast.service';
import { ConfirmService, EtatVideComponent, EtoilesComponent, SaisieNoteComponent } from './ui';

/**
 * Avis d'une salle, d'un coach ou d'une séance (UC-12) : note moyenne, liste des avis visibles
 * et formulaire de l'utilisateur connecté (création, modification, suppression).
 *
 * Les commentaires sont affichés par interpolation (texte), jamais en HTML : protection XSS (US-S06).
 */
@Component({
  selector: 'app-avis-section',
  imports: [DatePipe, FormsModule, RouterLink, EtoilesComponent, SaisieNoteComponent, EtatVideComponent],
  template: `
    <section class="panel avis-section" aria-labelledby="avis-titre">
      <div class="row row--between">
        <h2 id="avis-titre">Avis</h2>
        <app-etoiles [note]="note()?.note" [nbAvis]="note()?.nbAvis ?? 0" />
      </div>

      @if (auth.estConnecte()) {
        <form class="form avis-form" (ngSubmit)="envoyer()">
          <h3>{{ monAvis() ? 'Modifier mon avis' : 'Donner mon avis' }}</h3>
          <app-saisie-note [(valeur)]="saisieNote" [nom]="'note-' + type() + '-' + cibleId()" />
          <label class="field">
            <span>Commentaire (facultatif)</span>
            <textarea name="commentaire" [ngModel]="commentaire()" (ngModelChange)="commentaire.set($event)" maxlength="1000" rows="3"
              placeholder="Partagez votre expérience"></textarea>
            <span class="field-hint">{{ commentaire().length }} / 1000</span>
          </label>
          @if (erreur()) { <p class="alert alert--error" role="alert">{{ erreur() }}</p> }
          <div class="row">
            <button class="btn" type="submit" [disabled]="envoi() || saisieNote() < 1" [attr.aria-busy]="envoi()">
              {{ monAvis() ? 'Enregistrer' : 'Publier mon avis' }}
            </button>
            @if (monAvis()) {
              <button class="btn btn--ghost" type="button" [disabled]="envoi()" (click)="supprimer()">Supprimer mon avis</button>
            }
          </div>
        </form>
      } @else {
        <p class="text"><a [routerLink]="['/connexion']" [queryParams]="{ returnUrl: router.url }">Connectez-vous</a> pour donner votre avis.</p>
      }

      @for (a of avis(); track a.id) {
        <article class="avis-item">
          <div class="row row--between">
            <strong>{{ a.auteur ?? 'Membre FitBook' }}</strong>
            <time class="muted" [attr.datetime]="a.date">{{ a.date | date: 'd MMMM y' }}</time>
          </div>
          <app-etoiles [note]="a.note" [avecTexte]="false" />
          @if (a.commentaire) { <p class="avis-texte">{{ a.commentaire }}</p> }
        </article>
      } @empty {
        <app-etat-vide titre="Pas encore d’avis" message="Les participants pourront bientôt partager leur expérience." icone="★" />
      }
    </section>
  `,
  styles: `
    .avis-section h2 { margin: 0; }
    .avis-form { margin: 18px 0 8px; padding: 18px; border-radius: 14px; background: var(--surface-alt); }
    .avis-form h3 { margin: 0; font-size: 17px; }
    .avis-item { padding: 16px 0; border-top: 1px solid var(--border); }
    .avis-texte { margin: 6px 0 0; color: var(--text); white-space: pre-line; overflow-wrap: anywhere; }
  `,
})
export class AvisSectionComponent {
  readonly type = input.required<TypeCible>();
  readonly cibleId = input.required<number>();
  readonly note = input<Note | null>(null);
  readonly avis = input<Avis[]>([]);
  /** Un avis a été publié, modifié ou supprimé : le parent recharge la fiche. */
  readonly change = output<void>();

  readonly auth = inject(AuthService);
  readonly router = inject(Router);
  private readonly compte = inject(CompteService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  readonly mesAvis = signal<Avis[]>([]);
  readonly monAvis = computed(() => this.mesAvis().find(a => a.cible?.type === this.type() && a.cible?.id === this.cibleId()));
  readonly saisieNote = signal(0);
  readonly envoi = signal(false);
  readonly erreur = signal('');
  readonly commentaire = signal('');

  constructor() {
    // Avis existant de l'utilisateur sur cette cible : le formulaire passe en modification.
    effect(() => {
      this.cibleId();
      if (this.auth.estConnecte()) this.chargerMesAvis();
    });
  }

  envoyer(): void {
    if (this.envoi() || this.saisieNote() < 1) return;
    this.erreur.set('');
    this.envoi.set(true);
    const existant = this.monAvis();
    const requete: Observable<Avis> = existant
      ? this.compte.modifierAvis(existant.id, this.saisieNote(), this.commentaire().trim())
      : this.compte.donnerAvis(this.type(), this.cibleId(), this.saisieNote(), this.commentaire().trim());

    requete.subscribe({
      next: () => {
        this.envoi.set(false);
        this.toasts.succes(existant ? 'Votre avis a été modifié.' : 'Merci, votre avis est publié.');
        this.chargerMesAvis();
        this.change.emit();
      },
      error: (error: unknown) => {
        this.envoi.set(false);
        const status = error instanceof HttpErrorResponse ? error.status : 0;
        if (status === 403) {
          this.erreur.set('Vous devez avoir participé à une séance pour donner un avis.');
        } else if (status === 409) {
          // Un avis existe déjà sur cette cible : on bascule en modification.
          this.erreur.set('Vous avez déjà donné votre avis : modifiez-le ci-dessous.');
          this.chargerMesAvis(false);
        } else {
          this.erreur.set(messageErreur(error));
        }
      },
    });
  }

  async supprimer(): Promise<void> {
    const existant = this.monAvis();
    if (!existant) return;
    const ok = await this.confirm.demander({ titre: 'Supprimer votre avis ?', message: 'Votre note et votre commentaire seront retirés.', confirmer: 'Supprimer', danger: true });
    if (!ok) return;
    this.envoi.set(true);
    this.compte.supprimerAvis(existant.id).subscribe({
      next: () => {
        this.envoi.set(false);
        this.saisieNote.set(0);
        this.commentaire.set('');
        this.mesAvis.update(liste => liste.filter(a => a.id !== existant.id));
        this.toasts.succes('Votre avis a été supprimé.');
        this.change.emit();
      },
      error: (error: unknown) => {
        this.envoi.set(false);
        this.toasts.erreur(messageErreur(error));
      },
    });
  }

  /** @param preremplir recopie la note et le commentaire existants dans le formulaire */
  private chargerMesAvis(preremplir = true): void {
    this.compte.mesAvis().subscribe({
      next: liste => {
        this.mesAvis.set(liste);
        const existant = this.monAvis();
        if (existant && (preremplir || this.saisieNote() === 0)) {
          this.saisieNote.set(existant.note);
          this.commentaire.set(existant.commentaire ?? '');
        }
      },
      error: () => this.mesAvis.set([]),
    });
  }
}
