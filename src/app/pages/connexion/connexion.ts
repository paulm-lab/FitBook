import { HttpErrorResponse } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { defer, finalize, take } from 'rxjs';
import { AuthService } from '../../services/auth-service';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({
  selector: 'app-connexion',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, VisualShowcaseComponent],
  templateUrl: './connexion.html',
  styleUrl: './connexion.css'
})
export class ConnexionComponent {
  erreur = '';
  envoi = false;
  readonly formulaire;
  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);

  constructor(
    private readonly fb: FormBuilder,
    private readonly auth: AuthService,
    private readonly router: Router,
    private readonly route: ActivatedRoute
  ) {
    this.formulaire = this.fb.nonNullable.group({
      email: ['', [Validators.required, Validators.email, Validators.maxLength(100)]],
      password: ['', [Validators.required, Validators.minLength(8)]]
    });
  }

  connecter(): void {
    if (this.envoi) return;
    this.erreur = '';
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.envoi = true;
    const valeurs = this.formulaire.getRawValue();
    defer(() => this.auth.connexion(valeurs.email.trim(), valeurs.password)).pipe(
      take(1),
      finalize(() => { this.envoi = false; this.cdr.markForCheck(); })
    ).subscribe({
      next: () => {
        const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
        const destination = returnUrl?.startsWith('/') && !returnUrl.startsWith('//') ? returnUrl : '/';
        void this.router.navigateByUrl(destination);
      },
      error: (error: unknown) => {
        const status = error instanceof HttpErrorResponse ? error.status : -1;
        const message = error instanceof HttpErrorResponse && typeof error.error?.message === 'string' ? error.error.message : '';
        this.erreur = status === 401
          ? 'Adresse e-mail ou mot de passe incorrect.'
          : status === 403 && message
            ? message
            : status === 0
              ? 'Le serveur FitBook est injoignable. Vérifiez votre connexion et réessayez.'
              : 'Connexion impossible. Réessayez plus tard.';
      }
    });
  }
}
