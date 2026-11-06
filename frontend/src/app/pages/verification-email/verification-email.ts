import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { EMPTY, TimeoutError, catchError, defer, finalize, take } from 'rxjs';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';
import { AuthService } from '../../core/auth/auth.service';
import { MESSAGE_INJOIGNABLE, serveurInjoignable } from '../../core/http';
import { LangueService } from '../../core/i18n/langue.service';
import { I18N } from '../../core/i18n/pipes';

/**
 * Résultat du lien signé de confirmation (US-V06) : l'API active le compte puis redirige ici
 * avec ?status=success ou ?status=error. En cas d'échec, un nouveau lien peut être demandé.
 */
@Component({
  selector: 'app-verification-email',
  imports: [ReactiveFormsModule, RouterLink, VisualShowcaseComponent, ...I18N],
  templateUrl: './verification-email.html',
  styleUrl: './verification-email.css',
})
export class VerificationEmailComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly langue = inject(LangueService);

  /** Paramètre ?status= (withComponentInputBinding) : « success » ou toute autre valeur. */
  readonly status = input<string>();
  /** Lien valide, compte activé ; sinon lien invalide, modifié ou expiré. */
  readonly succes = computed(() => this.status() === 'success');

  readonly envoi = signal(false);
  readonly confirmation = signal('');
  readonly erreur = signal('');
  readonly formulaire = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
  });

  renvoyerLien(): void {
    if (this.envoi() || this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.envoi.set(true);
    this.erreur.set('');
    this.confirmation.set('');
    const email = this.formulaire.controls.email.value.trim();
    defer(() => this.auth.renvoyerVerification(email))
      .pipe(
        take(1),
        catchError((error: unknown) => {
          this.erreur.set(this.messageErreur(error));
          return EMPTY;
        }),
        finalize(() => this.envoi.set(false)),
      )
      // Message de l'API, déjà traduit selon Accept-Language.
      .subscribe((response) => this.confirmation.set(response.message));
  }

  private messageErreur(error: unknown): string {
    const t = (texte: string) => this.langue.t(texte);
    if (error instanceof TimeoutError) {
      return t('Le serveur met trop de temps à répondre. Réessayez dans quelques instants.');
    }
    if (error instanceof HttpErrorResponse) {
      if (serveurInjoignable(error)) return t(MESSAGE_INJOIGNABLE);
      if (typeof error.error?.message === 'string') return error.error.message;
    }
    return t('La demande de renvoi n’a pas abouti. Réessayez dans quelques instants.');
  }
}
