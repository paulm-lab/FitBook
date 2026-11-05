import { HttpErrorResponse } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { catchError, defer, EMPTY, finalize, take, TimeoutError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({
  selector: 'app-verification-email',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, VisualShowcaseComponent],
  templateUrl: './verification-email.html',
  styleUrl: './verification-email.css'
})
export class VerificationEmailComponent {
  readonly success: boolean;
  readonly formulaire;
  envoi = false;
  confirmation = '';
  erreur = '';
  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);

  constructor(route: ActivatedRoute, private readonly fb: FormBuilder, private readonly auth: AuthService) {
    this.success = route.snapshot.queryParamMap.get('status') === 'success';
    this.formulaire = this.fb.nonNullable.group({
      email: ['', [Validators.required, Validators.email, Validators.maxLength(100)]]
    });
  }

  renvoyerLien(): void {
    if (this.envoi || this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.envoi = true;
    this.erreur = '';
    this.confirmation = '';
    const email = this.formulaire.controls.email.value.trim();
    defer(() => this.auth.renvoyerVerification(email)).pipe(
      take(1),
      catchError((error: unknown) => {
        this.erreur = error instanceof TimeoutError
          ? 'Le serveur met trop de temps à répondre. Réessayez dans quelques instants.'
          : error instanceof HttpErrorResponse && typeof error.error?.message === 'string'
            ? error.error.message
            : 'La demande de renvoi n’a pas abouti. Réessayez dans quelques instants.';
        return EMPTY;
      }),
      finalize(() => { this.envoi = false; this.cdr.markForCheck(); })
    ).subscribe(response => { this.confirmation = response.message; });
  }
}
