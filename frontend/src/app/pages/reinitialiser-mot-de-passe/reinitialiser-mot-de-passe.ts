import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { erreursChamps, messageErreur } from '../../core/http';
import { ToastService } from '../../core/services/toast.service';
import { LangueService } from '../../core/i18n/langue.service';

import { I18N } from '../../core/i18n/pipes';

/** Les deux saisies du mot de passe doivent être identiques. */
function identiques(groupe: AbstractControl): ValidationErrors | null {
  const { password, confirmation } = groupe.value as { password: string; confirmation: string };
  return password && confirmation && password !== confirmation ? { differents: true } : null;
}

/** Choix d'un nouveau mot de passe depuis le lien reçu par e-mail (?token=…, usage unique, 1 h). */
@Component({
  selector: 'app-reinitialiser-mot-de-passe',
  imports: [...I18N, ReactiveFormsModule, RouterLink],
  template: `
    <main class="form-page" id="contenu">
      <section class="form-card" aria-labelledby="reset-title">
        <span class="section-kicker">{{ 'NOUVEAU MOT DE PASSE' | t }}</span>
        <h1 id="reset-title">{{ 'Choisir un nouveau mot de passe' | t }}</h1>

        @if (!token) {
          <p class="error-message" role="alert">
            {{
              'Ce lien est incomplet. Utilisez le lien reçu par e-mail ou faites une nouvelle demande.'
                | t
            }}
          </p>
          <a class="form-action" routerLink="/mot-de-passe-oublie">{{ 'Nouvelle demande' | t }}</a>
        } @else if (succes()) {
          <div class="alert alert--success" role="status">
            <p>{{ succes() }}</p>
          </div>
          <a class="form-action" routerLink="/connexion">{{ 'Se connecter' | t }}</a>
        } @else if (lienInvalide()) {
          <p class="error-message" role="alert">
            {{ 'Ce lien de réinitialisation est invalide ou a expiré.' | t }}
          </p>
          <a class="form-action" routerLink="/mot-de-passe-oublie">{{
            'Faire une nouvelle demande' | t
          }}</a>
        } @else {
          <p>
            {{
              '8 caractères minimum. Après la modification, connectez-vous avec ce nouveau mot de passe.'
                | t
            }}
          </p>
          <form [formGroup]="formulaire" (ngSubmit)="valider()" novalidate>
            <label for="reset-password">
              {{ 'Nouveau mot de passe' | t }}
              <input
                id="reset-password"
                type="password"
                formControlName="password"
                autocomplete="new-password"
                [attr.aria-invalid]="!!erreurPassword()"
                [attr.aria-describedby]="erreurPassword() ? 'reset-password-error' : null"
              />
              @if (erreurPassword()) {
                <small id="reset-password-error">{{ erreurPassword() }}</small>
              }
            </label>
            <label for="reset-confirmation">
              {{ 'Confirmez le mot de passe' | t }}
              <input
                id="reset-confirmation"
                type="password"
                formControlName="confirmation"
                autocomplete="new-password"
                [attr.aria-invalid]="!!erreurConfirmation()"
                [attr.aria-describedby]="erreurConfirmation() ? 'reset-confirmation-error' : null"
              />
              @if (erreurConfirmation()) {
                <small id="reset-confirmation-error">{{ erreurConfirmation() }}</small>
              }
            </label>
            @if (erreur()) {
              <p class="error-message" role="alert">{{ erreur() }}</p>
            }
            <button
              class="form-action"
              type="submit"
              [disabled]="envoi()"
              [attr.aria-busy]="envoi()"
            >
              {{ (envoi() ? 'Enregistrement…' : 'Enregistrer le mot de passe') | t }}
            </button>
          </form>
        }
      </section>
    </main>
  `,
})
export class ReinitialiserMotDePasseComponent {
  private readonly auth = inject(AuthService);
  private readonly toasts = inject(ToastService);
  private readonly langue = inject(LangueService);

  readonly token = inject(ActivatedRoute).snapshot.queryParamMap.get('token') ?? '';
  readonly envoi = signal(false);
  readonly succes = signal('');
  readonly erreur = signal('');
  readonly lienInvalide = signal(false);
  readonly erreurServeur = signal('');

  readonly formulaire = inject(FormBuilder).nonNullable.group(
    {
      password: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(4096)]],
      confirmation: ['', [Validators.required]],
    },
    { validators: identiques },
  );

  erreurPassword(): string {
    const champ = this.formulaire.controls.password;
    if (champ.touched && champ.hasError('required'))
      return this.langue.t('Saisissez un mot de passe.');
    if (champ.touched && champ.hasError('minlength'))
      return this.langue.t('Le mot de passe doit contenir au moins 8 caractères.');
    return this.erreurServeur();
  }

  erreurConfirmation(): string {
    const champ = this.formulaire.controls.confirmation;
    if (!champ.touched) return '';
    if (champ.hasError('required')) return this.langue.t('Confirmez le mot de passe.');
    return this.formulaire.hasError('differents')
      ? this.langue.t('Les deux mots de passe ne sont pas identiques.')
      : '';
  }

  valider(): void {
    if (this.envoi()) return;
    this.erreur.set('');
    this.erreurServeur.set('');
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }
    this.envoi.set(true);
    this.auth
      .reinitialiserMotDePasse(this.token, this.formulaire.controls.password.value)
      .pipe(finalize(() => this.envoi.set(false)))
      .subscribe({
        next: (response) => {
          this.succes.set(response.message);
          this.toasts.succes(this.langue.t('Mot de passe modifié. Vous pouvez vous connecter.'));
        },
        error: (error: unknown) => {
          if (error instanceof HttpErrorResponse && error.status === 400) {
            this.lienInvalide.set(true);
            return;
          }
          this.erreurServeur.set(erreursChamps(error)['password'] ?? '');
          this.erreur.set(
            messageErreur(error, 'Le mot de passe n’a pas pu être modifié. Réessayez.'),
          );
        },
      });
  }
}
