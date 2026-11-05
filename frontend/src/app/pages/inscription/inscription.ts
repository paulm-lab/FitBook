import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { HttpErrorResponse } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { catchError, defer, EMPTY, finalize, take, TimeoutError } from 'rxjs';
import { AuthService, InscriptionResponse, StatutEmailVerification } from '../../services/auth-service';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

type ChampInscription = 'prenom' | 'nom' | 'email' | 'telephone' | 'password';

@Component({
  selector: 'app-inscription',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, RouterLink, VisualShowcaseComponent],
  templateUrl: './inscription.html',
  styleUrl: './inscription.css'
})
export class InscriptionComponent {
  erreur = '';
  confirmation = '';
  emailInscrit = '';
  lienEnFile = false;
  // null après un renvoi : la réponse volontairement neutre du backend ne dit pas si l'e-mail est parti.
  statutEmail: StatutEmailVerification | null = null;
  envoi = false;
  erreursServeur: Partial<Record<ChampInscription, string>> = {};
  readonly formulaire;
  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);

  constructor(private readonly fb: FormBuilder, private readonly auth: AuthService) {
    this.formulaire = this.fb.nonNullable.group({
      prenom: ['', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(50)]],
      nom: ['', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(50)]],
      email: ['', [Validators.required, Validators.email, Validators.maxLength(100)]],
      telephone: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^\+?[0-9\s().-]{7,20}$/)]],
      password: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(4096)]]
    });
  }

  inscrire(): void {
    if (this.envoi) return;
    this.erreur = '';
    this.erreursServeur = {};
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    const valeurs = this.formulaire.getRawValue();
    const donnees = {
      ...valeurs,
      prenom: valeurs.prenom.trim(),
      nom: valeurs.nom.trim(),
      email: valeurs.email.trim(),
      telephone: valeurs.telephone.trim()
    };
    this.envoi = true;

    defer(() => this.auth.inscription(donnees)).pipe(
      catchError((error: unknown) => {
        if (error instanceof TimeoutError) {
          this.erreur = 'Le serveur met trop de temps \u{e0} r\u{e9}pondre. V\u{e9}rifiez votre connexion avant de r\u{e9}essayer.';
        } else if (error instanceof HttpErrorResponse) {
          const erreurs = error.error?.errors;
          if (erreurs && typeof erreurs === 'object') this.erreursServeur = erreurs as Partial<Record<ChampInscription, string>>;
          if (error.status === 409) this.erreursServeur.email = 'Cette adresse e-mail est d\u{e9}j\u{e0} utilis\u{e9}e.';
          this.erreur = typeof error.error?.message === 'string'
            ? error.error.message
            : error.status === 0
              ? 'Le serveur FitBook est injoignable. Si le compte a \u{e9}t\u{e9} cr\u{e9}\u{e9}, utilisez le lien re\u{e7}u par e-mail ou demandez-en un nouveau.'
              : 'Une erreur technique emp\u{ea}che la cr\u{e9}ation du compte. R\u{e9}essayez plus tard.';
        } else {
          this.erreur = 'Une erreur technique emp\u{ea}che la cr\u{e9}ation du compte. R\u{e9}essayez plus tard.';
        }
        return EMPTY;
      }),
      take(1),
      finalize(() => { this.envoi = false; this.cdr.markForCheck(); })
    ).subscribe({
      next: response => {
        this.emailInscrit = typeof response.email === 'string' && response.email ? response.email : donnees.email;
        this.statutEmail = InscriptionComponent.statutDepuisReponse(response);
        this.lienEnFile = this.statutEmail !== 'failed';
        this.confirmation = this.statutEmail === 'failed'
          ? `Compte cr\u{e9}\u{e9}. L'e-mail de v\u{e9}rification n'a pas pu \u{ea}tre envoy\u{e9}.`
          : 'Compte cr\u{e9}\u{e9}.';
      }
    });
  }

  // Sans statut explicite (ancien backend), on n'affirme jamais que l'e-mail est parti.
  private static statutDepuisReponse(response: InscriptionResponse): StatutEmailVerification {
    const statut = response.verificationEmailStatus;
    if (statut === 'sent' || statut === 'queued' || statut === 'failed') return statut;
    return response.verificationEmailQueued === true ? 'queued' : 'failed';
  }
  renvoyerLien(): void {
    if (this.envoi || !this.emailInscrit) return;
    this.envoi = true;
    this.erreur = '';
    this.auth.renvoyerVerification(this.emailInscrit).pipe(
      finalize(() => { this.envoi = false; this.cdr.markForCheck(); })
    ).subscribe({
      next: response => { this.confirmation = response.message; this.lienEnFile = true; this.statutEmail = null; },
      error: (error: unknown) => {
        if (error instanceof TimeoutError) {
          this.erreur = 'Le serveur met trop de temps à répondre. Vérifiez votre connexion avant de réessayer.';
          return;
        }
        if (!(error instanceof HttpErrorResponse)) {
          this.erreur = 'Le lien de vérification n’a pas pu être demandé. Réessayez plus tard.';
          return;
        }
        this.erreur = typeof error.error?.message === 'string'
          ? error.error.message
          : error.status === 0
            ? 'Le serveur FitBook est injoignable. Vérifiez votre connexion et réessayez.'
            : 'Le lien de vérification n’a pas pu être envoyé. Réessayez plus tard.';
      }
    });
  }

  erreurChamp(champ: ChampInscription): string {
    const control = this.formulaire.controls[champ];
    if (control.touched || control.dirty) {
      if (control.hasError('required')) return 'Ce champ est obligatoire.';
      if (control.hasError('email')) return 'Saisissez une adresse e-mail valide.';
      if (control.hasError('maxlength')) return 'La valeur saisie est trop longue.';
      if (control.hasError('minlength')) return 'Le mot de passe doit contenir au moins 8 caractères.';
      if (control.hasError('pattern')) return champ === 'telephone' ? 'Saisissez un numéro de téléphone valide.' : 'Ce champ ne peut pas être vide.';
    }
    return this.erreursServeur[champ] ?? '';
  }

  effacerErreurServeur(champ: ChampInscription): void {
    delete this.erreursServeur[champ];
  }
}
