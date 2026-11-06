import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { defer, finalize, take } from 'rxjs';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';
import { MESSAGE_INJOIGNABLE, serveurInjoignable } from '../../core/http';
import { AuthService } from '../../core/auth/auth.service';
import { LangueService } from '../../core/i18n/langue.service';
import { I18N } from '../../core/i18n/pipes';

/** Connexion par e-mail et mot de passe (US-S01) : jeton JWT posé par l'API dans un cookie HttpOnly. */
@Component({
  selector: 'app-connexion',
  imports: [ReactiveFormsModule, RouterLink, VisualShowcaseComponent, ...I18N],
  templateUrl: './connexion.html',
  styleUrl: './connexion.css',
})
export class ConnexionComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly langue = inject(LangueService);

  readonly erreur = signal('');
  readonly envoi = signal(false);

  readonly formulaire = this.fb.nonNullable.group({
    // Pré-rempli quand on arrive depuis la confirmation d'inscription.
    email: [
      this.route.snapshot.queryParamMap.get('email') ?? '',
      [Validators.required, Validators.email, Validators.maxLength(180)],
    ],
    password: ['', [Validators.required, Validators.minLength(8)]],
  });

  connecter(): void {
    if (this.envoi()) return;
    this.erreur.set('');
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.envoi.set(true);
    const valeurs = this.formulaire.getRawValue();
    defer(() => this.auth.connexion(valeurs.email.trim(), valeurs.password))
      .pipe(
        take(1),
        finalize(() => this.envoi.set(false)),
      )
      .subscribe({
        next: () => void this.router.navigateByUrl(this.destination()),
        error: (error: unknown) => this.erreur.set(this.messageErreur(error)),
      });
  }

  /** Page demandée avant la connexion, uniquement si c'est une adresse interne au site. */
  private destination(): string {
    const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl');
    return returnUrl?.startsWith('/') &&
      !returnUrl.startsWith('//') &&
      !returnUrl.startsWith('/connexion')
      ? returnUrl
      : this.auth.espace();
  }

  private messageErreur(error: unknown): string {
    const t = (texte: string) => this.langue.t(texte);
    if (!(error instanceof HttpErrorResponse))
      return t('Connexion impossible. Réessayez plus tard.');
    if (serveurInjoignable(error)) return t(MESSAGE_INJOIGNABLE);
    const message = typeof error.error?.message === 'string' ? error.error.message : '';
    switch (error.status) {
      case 401:
        return t('Adresse e-mail ou mot de passe incorrect.');
      case 403:
        return message || t('Ce compte ne peut pas se connecter.');
      case 429:
        return (
          message ||
          t('Trop de tentatives de connexion. Patientez quelques minutes avant de réessayer.')
        );
      default:
        return t('Connexion impossible. Réessayez plus tard.');
    }
  }
}
