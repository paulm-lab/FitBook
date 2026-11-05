import { Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '../../../core/auth/auth.service';
import { erreursChamps, messageErreur } from '../../../core/http';
import { ROLES, Utilisateur } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { ToastService } from '../../../core/services/toast.service';
import { ConfirmService, ImageSecoursDirective } from '../../../shared/ui';

type ChampProfil = 'nom' | 'prenom' | 'telephone';

const TYPES_AVATAR = ['image/jpeg', 'image/png', 'image/webp'];
const TAILLE_MAX_AVATAR = 2 * 1024 * 1024;

/**
 * Profil du compte (US-S07), commun à tous les rôles : prénom, nom, téléphone et avatar.
 * L'e-mail et le rôle ne sont pas modifiables. Le type et la taille de l'avatar sont vérifiés
 * ici pour un retour immédiat, puis de nouveau par l'API (contenu réel du fichier).
 */
@Component({
  selector: 'app-profil',
  imports: [ReactiveFormsModule, RouterLink, ImageSecoursDirective],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Mon compte</span>
        <h1>Mon profil</h1>
        <p>Ces informations sont utilisées pour vos réservations.</p>
      </div>
    </header>

    <div class="profile">
      <section class="panel avatar-panel" aria-labelledby="avatar-title">
        <h2 id="avatar-title">Photo de profil</h2>
        @if (auth.utilisateur()?.avatar) {
          <img class="avatar avatar--lg" [src]="auth.utilisateur()!.avatar" alt="Votre photo de profil" appSecours>
        } @else {
          <span class="avatar avatar--lg avatar--initiales" aria-hidden="true">{{ initiales() }}</span>
        }
        <label class="btn btn--sm btn--ghost file-btn" [class.disabled]="envoiAvatar()">
          {{ envoiAvatar() ? 'Envoi…' : 'Choisir une image' }}
          <input type="file" accept="image/jpeg,image/png,image/webp" (change)="choisirAvatar($event)" [disabled]="envoiAvatar()"
            aria-describedby="avatar-hint avatar-error">
        </label>
        <small class="field-hint" id="avatar-hint">JPEG, PNG ou WebP, 2 Mo au maximum.</small>
        @if (erreurAvatar()) { <small class="field-error" id="avatar-error" role="alert">{{ erreurAvatar() }}</small> }
        @if (auth.utilisateur()?.avatar) {
          <button type="button" class="link-btn" (click)="supprimerAvatar()" [disabled]="envoiAvatar()">Supprimer la photo</button>
        }
      </section>

      <section class="panel" aria-labelledby="infos-title">
        <h2 id="infos-title">Mes informations</h2>
        <form class="form" [formGroup]="formulaire" (ngSubmit)="enregistrer()" novalidate>
          <div class="form-row">
            <label class="field">
              <span>Prénom</span>
              <input type="text" formControlName="prenom" autocomplete="given-name"
                [attr.aria-invalid]="!!erreurChamp('prenom')" aria-describedby="profil-prenom-error">
              @if (erreurChamp('prenom')) { <small class="field-error" id="profil-prenom-error">{{ erreurChamp('prenom') }}</small> }
            </label>
            <label class="field">
              <span>Nom</span>
              <input type="text" formControlName="nom" autocomplete="family-name"
                [attr.aria-invalid]="!!erreurChamp('nom')" aria-describedby="profil-nom-error">
              @if (erreurChamp('nom')) { <small class="field-error" id="profil-nom-error">{{ erreurChamp('nom') }}</small> }
            </label>
          </div>
          <label class="field">
            <span>Téléphone</span>
            <input type="tel" formControlName="telephone" autocomplete="tel"
              [attr.aria-invalid]="!!erreurChamp('telephone')" aria-describedby="profil-telephone-error">
            @if (erreurChamp('telephone')) { <small class="field-error" id="profil-telephone-error">{{ erreurChamp('telephone') }}</small> }
          </label>
          <div class="form-row">
            <label class="field">
              <span>E-mail</span>
              <input type="email" [value]="auth.utilisateur()?.email ?? ''" disabled aria-describedby="profil-email-hint">
              <small class="field-hint" id="profil-email-hint">L'adresse e-mail ne peut pas être modifiée.</small>
            </label>
            <label class="field">
              <span>Rôle</span>
              <input type="text" [value]="roleLibelle()" disabled>
            </label>
          </div>
          <div class="row row--between">
            <a class="text-link" routerLink="/mot-de-passe-oublie">Changer de mot de passe</a>
            <button class="btn" type="submit" [disabled]="envoi() || formulaire.pristine" [attr.aria-busy]="envoi()">
              {{ envoi() ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </div>
        </form>
      </section>
    </div>
  `,
  styles: `
    .profile { display: grid; grid-template-columns: 280px minmax(0, 1fr); gap: 20px; align-items: start; }
    .avatar-panel { display: grid; justify-items: center; gap: 12px; text-align: center; }
    .avatar-panel h2 { justify-self: stretch; }
    .file-btn { position: relative; }
    .file-btn input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
    .file-btn:focus-within { outline: 3px solid var(--primary); outline-offset: 2px; }
    .file-btn.disabled { opacity: .55; }
    @media (max-width: 900px) { .profile { grid-template-columns: 1fr; } }
  `,
})
export class ProfilComponent {
  readonly auth = inject(AuthService);
  private readonly compte = inject(CompteService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  readonly envoi = signal(false);
  readonly envoiAvatar = signal(false);
  readonly erreurAvatar = signal('');
  readonly erreursServeur = signal<Partial<Record<ChampProfil, string>>>({});
  readonly roleLibelle = computed(() => (this.auth.role() ? ROLES[this.auth.role()!] : ''));

  readonly formulaire = inject(FormBuilder).nonNullable.group({
    prenom: [this.auth.utilisateur()?.prenom ?? '', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(50)]],
    nom: [this.auth.utilisateur()?.nom ?? '', [Validators.required, Validators.pattern(/\S/), Validators.maxLength(50)]],
    telephone: [this.auth.utilisateur()?.telephone ?? '', [Validators.required, Validators.pattern(/^\+?[0-9\s().-]{7,20}$/)]],
  });

  initiales(): string {
    const u = this.auth.utilisateur();
    return `${u?.prenom?.[0] ?? ''}${u?.nom?.[0] ?? ''}`.toUpperCase();
  }

  erreurChamp(champ: ChampProfil): string {
    const control = this.formulaire.controls[champ];
    if (control.touched || control.dirty) {
      if (control.hasError('required') || control.hasError('pattern')) {
        return champ === 'telephone' ? 'Saisissez un numéro valide (7 à 20 caractères).' : 'Ce champ est obligatoire.';
      }
      if (control.hasError('maxlength')) return '50 caractères au maximum.';
    }
    return this.erreursServeur()[champ] ?? '';
  }

  enregistrer(): void {
    if (this.envoi()) return;
    this.erreursServeur.set({});
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }
    const v = this.formulaire.getRawValue();
    this.envoi.set(true);
    this.compte.modifierProfil({ prenom: v.prenom.trim(), nom: v.nom.trim(), telephone: v.telephone.trim() })
      .pipe(finalize(() => this.envoi.set(false)))
      .subscribe({
        next: user => {
          this.mettreAJour(user);
          this.formulaire.markAsPristine();
          this.toasts.succes('Profil enregistré.');
        },
        error: (error: unknown) => {
          this.erreursServeur.set(erreursChamps(error));
          this.toasts.erreur(messageErreur(error, 'Le profil n’a pas pu être enregistré.'));
        },
      });
  }

  choisirAvatar(event: Event): void {
    const champ = event.target as HTMLInputElement;
    const fichier = champ.files?.[0];
    champ.value = '';
    this.erreurAvatar.set('');
    if (!fichier) return;
    if (!TYPES_AVATAR.includes(fichier.type)) {
      this.erreurAvatar.set('Formats acceptés : JPEG, PNG ou WebP.');
      return;
    }
    if (fichier.size > TAILLE_MAX_AVATAR) {
      this.erreurAvatar.set('L’image ne doit pas dépasser 2 Mo.');
      return;
    }

    this.envoiAvatar.set(true);
    this.compte.envoyerAvatar(fichier).pipe(finalize(() => this.envoiAvatar.set(false))).subscribe({
      next: user => {
        this.mettreAJour(user);
        this.toasts.succes('Photo de profil mise à jour.');
      },
      error: (error: unknown) => this.erreurAvatar.set(messageErreur(error, 'L’image n’a pas pu être envoyée.')),
    });
  }

  async supprimerAvatar(): Promise<void> {
    const ok = await this.confirm.demander({ titre: 'Supprimer la photo ?', message: 'Vos initiales seront affichées à la place.', confirmer: 'Supprimer', danger: true });
    if (!ok) return;
    this.envoiAvatar.set(true);
    this.compte.supprimerAvatar().pipe(finalize(() => this.envoiAvatar.set(false))).subscribe({
      next: user => {
        this.mettreAJour(user);
        this.toasts.succes('Photo supprimée.');
      },
      error: (error: unknown) => this.toasts.erreur(messageErreur(error)),
    });
  }

  private mettreAJour(user: Utilisateur): void {
    this.auth.mettreAJour(user);
  }
}
