import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { messageErreur } from '../../core/http';

import { I18N } from '../../core/i18n/pipes';

/**
 * Demande d'un lien de réinitialisation (US-V07). L'API répond le même message que l'adresse
 * existe ou non : la page ne révèle jamais si un compte existe.
 */
@Component({
  selector: 'app-mot-de-passe-oublie',
  imports: [...I18N, ReactiveFormsModule, RouterLink],
  template: `
    <main class="form-page" id="contenu">
      <section class="form-card" aria-labelledby="forgot-title">
        <span class="section-kicker">{{ 'MOT DE PASSE OUBLIÉ' | t }}</span>
        <h1 id="forgot-title">{{ 'Réinitialiser mon mot de passe' | t }}</h1>
        <p>
          {{
            "Saisissez l'adresse de votre compte : nous vous enverrons un lien valable 1 heure." | t
          }}
        </p>

        @if (message()) {
          <div class="alert alert--success" role="status">
            <p>{{ message() }}</p>
            <p>{{ 'Pensez à vérifier vos courriers indésirables.' | t }}</p>
          </div>
          <p class="form-switch">
            <a routerLink="/connexion">{{ 'Retour à la connexion' | t }}</a>
          </p>
        } @else {
          <form [formGroup]="formulaire" (ngSubmit)="envoyer()" novalidate>
            <label for="forgot-email">
              {{ 'E-mail' | t }}
              <input
                id="forgot-email"
                type="email"
                formControlName="email"
                autocomplete="email"
                inputmode="email"
                [attr.aria-invalid]="invalide()"
                [attr.aria-describedby]="invalide() ? 'forgot-email-error' : null"
              />
              @if (invalide()) {
                <small id="forgot-email-error">{{
                  'Saisissez une adresse e-mail valide.' | t
                }}</small>
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
              {{ (envoi() ? 'Envoi…' : 'Recevoir le lien') | t }}
            </button>
          </form>
          <p class="form-switch">
            <a routerLink="/connexion">{{ 'Retour à la connexion' | t }}</a>
          </p>
        }
      </section>
    </main>
  `,
})
export class MotDePasseOublieComponent {
  private readonly auth = inject(AuthService);
  readonly formulaire = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
  });
  readonly envoi = signal(false);
  readonly message = signal('');
  readonly erreur = signal('');

  invalide(): boolean {
    const champ = this.formulaire.controls.email;
    return champ.touched && champ.invalid;
  }

  envoyer(): void {
    if (this.envoi()) return;
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }
    this.envoi.set(true);
    this.erreur.set('');
    this.auth
      .motDePasseOublie(this.formulaire.controls.email.value.trim())
      .pipe(finalize(() => this.envoi.set(false)))
      .subscribe({
        next: (response) => this.message.set(response.message),
        error: (error: unknown) =>
          this.erreur.set(
            messageErreur(error, 'La demande n’a pas abouti. Réessayez dans quelques instants.'),
          ),
      });
  }
}
