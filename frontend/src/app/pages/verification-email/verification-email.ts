import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, defer, EMPTY, finalize, take, TimeoutError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({
  selector: 'app-verification-email',
  imports: [ReactiveFormsModule, RouterLink, VisualShowcaseComponent],
  templateUrl: './verification-email.html',
  styleUrl: './verification-email.css'
})
export class VerificationEmailComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);

  /** ?status=success : lien valide, compte activé ; toute autre valeur : lien invalide ou expiré. */
  readonly success = inject(ActivatedRoute).snapshot.queryParamMap.get('status') === 'success';
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
    defer(() => this.auth.renvoyerVerification(email)).pipe(
      take(1),
      catchError((error: unknown) => {
        this.erreur.set(error instanceof TimeoutError
          ? 'Le serveur met trop de temps à répondre. Réessayez dans quelques instants.'
          : error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
            ? error.error.message
            : 'La demande de renvoi n’a pas abouti. Réessayez dans quelques instants.');
        return EMPTY;
      }),
      finalize(() => this.envoi.set(false))
    ).subscribe(response => this.confirmation.set(response.message));
  }
}
