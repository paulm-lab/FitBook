import { HttpErrorResponse } from '@angular/common/http';
import { CommonModule } from '@angular/common';
import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router } from '@angular/router';
import { ProfilService } from '../../services/profil-service';
import { UtilisateurConnecte } from '../../models/utilisateur';

@Component({
  selector: 'app-profil',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './profil.html',
  styleUrl: './profil.css'
})
export class ProfilComponent implements OnInit {
  profil: UtilisateurConnecte | null = null;
  chargement = true;
  envoi = false;
  erreur = '';
  confirmation = '';
  readonly formulaire;

  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);
  constructor(private readonly fb: FormBuilder, private readonly profils: ProfilService, private readonly router: Router) {
    this.formulaire = this.fb.nonNullable.group({
      nom: ['', [Validators.required, Validators.maxLength(50)]],
      prenom: ['', [Validators.required, Validators.maxLength(50)]],
      telephone: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^\+?[0-9\s().-]{7,20}$/)]]
    });
  }

  ngOnInit(): void {
    this.profils.getProfil().subscribe({
      next: profil => {
        this.profil = profil;
        this.formulaire.setValue({ nom: profil.nom, prenom: profil.prenom, telephone: profil.telephone ?? '' });
        this.chargement = false;
        this.cdr.markForCheck();
      },
      error: (error: HttpErrorResponse) => {
        this.chargement = false;
        if (error.status === 401) {
          void this.router.navigate(['/connexion'], { queryParams: { returnUrl: '/profil' } });
        } else {
          this.erreur = 'Impossible de charger votre profil. Réessayez plus tard.';
        }
        this.cdr.markForCheck();
      }
    });
  }

  enregistrer(): void {
    if (this.envoi) return;
    this.erreur = '';
    this.confirmation = '';
    if (this.formulaire.invalid) {
      this.formulaire.markAllAsTouched();
      return;
    }
    this.envoi = true;
    const profil = this.formulaire.getRawValue();
    this.profils.modifierProfil({ nom: profil.nom.trim(), prenom: profil.prenom.trim(), telephone: profil.telephone.trim() }).subscribe({
      next: updated => {
        this.profil = updated;
        this.formulaire.setValue({ nom: updated.nom, prenom: updated.prenom, telephone: updated.telephone ?? '' });
        this.confirmation = 'Votre profil a été mis à jour.';
        this.envoi = false;
        this.cdr.markForCheck();
      },
      error: (error: HttpErrorResponse) => {
        this.envoi = false;
        if (error.status === 401) {
          void this.router.navigate(['/connexion'], { queryParams: { returnUrl: '/profil' } });
        } else if (error.status === 422 && error.error?.errors) {
          const errors = error.error.errors as Record<string, string>;
          this.erreur = Object.values(errors).join(' ');
        } else {
          this.erreur = 'Impossible d’enregistrer votre profil. Vérifiez les champs puis réessayez.';
        }
        this.cdr.markForCheck();
      }
    });
  }
}
