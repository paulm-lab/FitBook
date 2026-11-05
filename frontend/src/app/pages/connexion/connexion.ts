import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { defer, finalize, take } from 'rxjs';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';
import { AuthService } from '../../core/auth/auth.service';

/** Connexion par e-mail et mot de passe (US-S01) : jeton JWT posé par l'API dans un cookie HttpOnly. */
@Component({
  selector: 'app-connexion',
  imports: [ReactiveFormsModule, RouterLink, VisualShowcaseComponent],
  templateUrl: './connexion.html',
  styleUrl: './connexion.css',
})
export class ConnexionComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly erreur = signal('');
  readonly envoi = signal(false);
  /** Vrai si l'API refuse la connexion parce que l'adresse n'est pas encore confirmée. */
  readonly nonVerifie = signal(false);
  readonly renvoiMessage = signal('');
  readonly renvoiEnCours = signal(false);

  readonly formulaire = this.fb.nonNullable.group({
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });

  connecter(): void {
    if (this.envoi()) return;
    this.erreur.set('');
    this.nonVerifie.set(false);
    this.renvoiMessage.set('');
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.envoi.set(true);
    const valeurs = this.formulaire.getRawValue();
    defer(() => this.auth.connexion(valeurs.email.trim(), valeurs.password)).pipe(
      take(1),
      finalize(() => this.envoi.set(false)),
    ).subscribe({
      next: () => void this.router.navigateByUrl(this.destination()),
      error: (error: unknown) => this.erreur.set(this.messageErreur(error)),
    });
  }

  /** Renvoie le lien de confirmation à l'adresse saisie (compte non vérifié). */
  renvoyerLien(): void {
    const email = this.formulaire.controls.email.value.trim();
    if (this.renvoiEnCours() || !email) return;
    this.renvoiEnCours.set(true);
    this.auth.renvoyerVerification(email).pipe(finalize(() => this.renvoiEnCours.set(false))).subscribe({
      next: response => this.renvoiMessage.set(response.message),
      error: () => this.renvoiMessage.set('Le lien n’a pas pu être renvoyé. Réessayez dans quelques minutes.'),
    });
  }

  /** Page demandée avant la connexion, uniquement si c'est une adresse interne au site. */
  private destination(): string {
    const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
    return returnUrl?.startsWith('/') && !returnUrl.startsWith('//') && !returnUrl.startsWith('/connexion')
      ? returnUrl
      : this.auth.espace();
  }

  private messageErreur(error: unknown): string {
    if (!(error instanceof HttpErrorResponse)) return 'Connexion impossible. Réessayez plus tard.';
    const message = typeof error.error?.message === 'string' ? error.error.message : '';
    switch (error.status) {
      case 401:
        return 'Adresse e-mail ou mot de passe incorrect.';
      case 403:
        this.nonVerifie.set(/confirm|vérifi/i.test(message));
        return message || 'Ce compte ne peut pas se connecter.';
      case 429:
        return message || 'Trop de tentatives de connexion. Patientez quelques minutes avant de réessayer.';
      case 0:
        return 'Le serveur FitBook est injoignable. Vérifiez votre connexion et réessayez.';
      default:
        return 'Connexion impossible. Réessayez plus tard.';
    }
  }
}
