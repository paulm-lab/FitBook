import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { EMPTY, TimeoutError, catchError, defer, finalize, take } from 'rxjs';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';
import { AuthService, InscriptionResponse, InscriptionSaisie, StatutEmailVerification } from '../../core/auth/auth.service';

type ChampInscription = 'prenom' | 'nom' | 'email' | 'telephone' | 'password' | 'role';
type RoleInscription = InscriptionSaisie['role'];

/** Rôles proposés à l'inscription (US-V05) : l'administrateur n'est jamais proposé. */
export const ROLES_INSCRIPTION: { valeur: RoleInscription; titre: string; description: string }[] = [
  { valeur: 'sportif', titre: 'Sportif', description: 'Je réserve des séances, je garde mes favoris et je donne mon avis.' },
  { valeur: 'coach', titre: 'Coach', description: 'Je propose mes séances, je gère mes disponibilités et mes inscrits.' },
  { valeur: 'gestionnaire', titre: 'Gestionnaire de salle', description: 'Je présente mes salles, je planifie leurs cours et je suis leurs statistiques.' },
];

/** Création de compte avec choix du rôle, puis confirmation de l'adresse par e-mail (US-V05, US-V06). */
@Component({
  selector: 'app-inscription',
  imports: [ReactiveFormsModule, RouterLink, VisualShowcaseComponent],
  templateUrl: './inscription.html',
  styleUrl: './inscription.css',
})
export class InscriptionComponent {
  private readonly fb = inject(FormBuilder);
  private readonly auth = inject(AuthService);

  readonly roles = ROLES_INSCRIPTION;
  readonly erreur = signal('');
  readonly confirmation = signal('');
  readonly emailInscrit = signal('');
  readonly lienEnFile = signal(false);
  // null après un renvoi : la réponse volontairement neutre du backend ne dit pas si l'e-mail est parti.
  readonly statutEmail = signal<StatutEmailVerification | null>(null);
  readonly envoi = signal(false);
  readonly erreursServeur = signal<Partial<Record<ChampInscription, string>>>({});

  readonly formulaire = this.fb.nonNullable.group({
    prenom: ['', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(50)]],
    nom: ['', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(50)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(180)]],
    telephone: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^\+?[0-9\s().-]{7,20}$/)]],
    password: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(4096)]],
    role: ['sportif' as RoleInscription, [Validators.required]],
  });

  inscrire(): void {
    if (this.envoi()) return;
    this.erreur.set('');
    this.erreursServeur.set({});
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }

    const valeurs = this.formulaire.getRawValue();
    const donnees: InscriptionSaisie = {
      ...valeurs,
      prenom: valeurs.prenom.trim(),
      nom: valeurs.nom.trim(),
      email: valeurs.email.trim(),
      telephone: valeurs.telephone.trim(),
      // Défense en profondeur : seul un rôle de la liste peut partir vers l'API.
      role: this.roles.some(r => r.valeur === valeurs.role) ? valeurs.role : 'sportif',
    };
    this.envoi.set(true);

    defer(() => this.auth.inscription(donnees)).pipe(
      catchError((error: unknown) => {
        this.traiterErreur(error);
        return EMPTY;
      }),
      take(1),
      finalize(() => this.envoi.set(false)),
    ).subscribe(response => {
      this.emailInscrit.set(typeof response.email === 'string' && response.email ? response.email : donnees.email);
      const statut = InscriptionComponent.statutDepuisReponse(response);
      this.statutEmail.set(statut);
      this.lienEnFile.set(statut !== 'failed');
      this.confirmation.set(statut === 'failed' ? 'Compte créé. L\'e-mail de vérification n\'a pas pu être envoyé.' : 'Compte créé.');
    });
  }

  renvoyerLien(): void {
    if (this.envoi() || !this.emailInscrit()) return;
    this.envoi.set(true);
    this.erreur.set('');
    this.auth.renvoyerVerification(this.emailInscrit()).pipe(finalize(() => this.envoi.set(false))).subscribe({
      next: response => {
        this.confirmation.set(response.message);
        this.lienEnFile.set(true);
        this.statutEmail.set(null);
      },
      error: (error: unknown) => {
        if (error instanceof TimeoutError) {
          this.erreur.set('Le serveur met trop de temps à répondre. Vérifiez votre connexion avant de réessayer.');
        } else if (!(error instanceof HttpErrorResponse)) {
          this.erreur.set('Le lien de vérification n’a pas pu être demandé. Réessayez plus tard.');
        } else {
          this.erreur.set(typeof error.error?.message === 'string'
            ? error.error.message
            : error.status === 0
              ? 'Le serveur FitBook est injoignable. Vérifiez votre connexion et réessayez.'
              : 'Le lien de vérification n’a pas pu être envoyé. Réessayez plus tard.');
        }
      },
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
    return this.erreursServeur()[champ] ?? '';
  }

  effacerErreurServeur(champ: ChampInscription): void {
    this.erreursServeur.update(erreurs => {
      const { [champ]: _, ...reste } = erreurs;
      return reste;
    });
  }

  private traiterErreur(error: unknown): void {
    if (error instanceof TimeoutError) {
      this.erreur.set('Le serveur met trop de temps à répondre. Vérifiez votre connexion avant de réessayer.');
      return;
    }
    if (!(error instanceof HttpErrorResponse)) {
      this.erreur.set('Une erreur technique empêche la création du compte. Réessayez plus tard.');
      return;
    }
    const erreurs = error.error?.errors;
    const parChamp: Partial<Record<ChampInscription, string>> = erreurs && typeof erreurs === 'object' ? { ...erreurs } : {};
    if (error.status === 409) parChamp.email = 'Cette adresse e-mail est déjà utilisée.';
    this.erreursServeur.set(parChamp);
    this.erreur.set(typeof error.error?.message === 'string'
      ? error.error.message
      : error.status === 0
        ? 'Le serveur FitBook est injoignable. Si le compte a été créé, utilisez le lien reçu par e-mail ou demandez-en un nouveau.'
        : 'Une erreur technique empêche la création du compte. Réessayez plus tard.');
  }

  // Sans statut explicite (ancien backend), on n'affirme jamais que l'e-mail est parti.
  private static statutDepuisReponse(response: InscriptionResponse): StatutEmailVerification {
    const statut = response.verificationEmailStatus;
    if (statut === 'sent' || statut === 'queued' || statut === 'failed') return statut;
    return response.verificationEmailQueued === true ? 'queued' : 'failed';
  }
}
