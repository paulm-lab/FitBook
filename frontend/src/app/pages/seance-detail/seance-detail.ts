import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, input, signal, untracked } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { messageErreur } from '../../core/http';
import { NIVEAUX, SeanceDetail } from '../../core/models';
import { CatalogueService } from '../../core/services/catalogue.service';
import { CompteService } from '../../core/services/compte.service';
import { ToastService } from '../../core/services/toast.service';
import { AvisSectionComponent } from '../../shared/avis-section';
import { FavoriButtonComponent } from '../../shared/favori-button';
import {
  EtatVideComponent,
  EtoilesComponent,
  ImageSecoursDirective,
  LoaderComponent,
} from '../../shared/ui';

import { I18N } from '../../core/i18n/pipes';
import { LangueService } from '../../core/i18n/langue.service';

type Etat = 'chargement' | 'ok' | 'introuvable' | 'erreur';

/** Fiche d'une séance et réservation (US-V04, UC-08). */
@Component({
  selector: 'app-seance-detail',
  imports: [
    ...I18N,
    RouterLink,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    ImageSecoursDirective,
    FavoriButtonComponent,
    AvisSectionComponent,
  ],
  templateUrl: './seance-detail.html',
  styleUrl: './seance-detail.css',
})
export class SeanceDetailComponent {
  /** Paramètre de route :id. */
  readonly id = input.required<string>();

  readonly auth = inject(AuthService);
  private readonly catalogue = inject(CatalogueService);
  private readonly compte = inject(CompteService);
  private readonly toasts = inject(ToastService);
  private readonly router = inject(Router);
  private readonly langue = inject(LangueService);

  readonly etat = signal<Etat>('chargement');
  readonly seance = signal<SeanceDetail | null>(null);
  readonly envoi = signal(false);
  /** Réservation faite depuis cette page : lien vers « Mes réservations ». */
  readonly reservee = signal(false);
  /** Place déjà réservée par l'utilisateur (API) ou depuis cette page : bouton désactivé. */
  readonly dejaReservee = computed(() => this.reservee() || !!this.seance()?.dejaReservee);

  readonly niveau = computed(() => (this.seance() ? NIVEAUX[this.seance()!.niveau] : ''));
  readonly complet = computed(() => (this.seance()?.placesDisponibles ?? 0) <= 0);
  readonly passee = computed(() => {
    const s = this.seance();
    return !!s && (s.statut === 'annulee' || new Date(s.dateDebut) <= new Date());
  });
  /** Séance active et finie : les participants peuvent donner leur avis. */
  readonly terminee = computed(() => {
    const s = this.seance();
    return !!s && s.statut === 'active' && new Date(s.dateFin) <= new Date();
  });
  readonly duree = computed(() => {
    const s = this.seance();
    return s
      ? Math.round((new Date(s.dateFin).getTime() - new Date(s.dateDebut).getTime()) / 60000)
      : 0;
  });
  /** Espace de gestion selon le rôle de l'organisateur connecté. */
  readonly lienGestion = computed(() => {
    const role = this.auth.role();
    const espace = role === 'admin' ? 'admin' : role === 'gestionnaire' ? 'gestionnaire' : 'coach';
    return ['/', espace, 'seances', this.id()];
  });

  private requete?: Subscription;

  constructor() {
    effect(() => {
      const id = this.id();
      // Autre séance : l'état « réservée » de la précédente ne s'applique plus.
      untracked(() => this.reservee.set(false));
      this.charger(id);
    });
  }

  charger(id: string, silencieux = false): void {
    if (!silencieux) this.etat.set('chargement');
    // Autre identifiant avant la réponse : la requête précédente est annulée et ne peut plus
    // écraser la fiche affichée.
    this.requete?.unsubscribe();
    this.requete = this.catalogue.seance(Number(id)).subscribe({
      next: (seance) => {
        this.seance.set(seance);
        this.etat.set('ok');
      },
      error: (error: unknown) => {
        const status = error instanceof HttpErrorResponse ? error.status : 0;
        this.etat.set(status === 404 || Number.isNaN(Number(id)) ? 'introuvable' : 'erreur');
      },
    });
  }

  reserver(): void {
    const seance = this.seance();
    if (!seance || this.envoi() || this.dejaReservee()) return;
    if (!this.auth.estConnecte()) {
      void this.router.navigate(['/connexion'], { queryParams: { returnUrl: this.router.url } });
      return;
    }

    this.envoi.set(true);
    this.compte.reserver(seance.id).subscribe({
      next: () => {
        this.envoi.set(false);
        this.reservee.set(true);
        this.seance.update((s) =>
          s ? { ...s, placesDisponibles: Math.max(0, s.placesDisponibles - 1) } : s,
        );
        this.toasts.succes(
          this.langue.t('Réservation confirmée ! Un e-mail de confirmation vous a été envoyé.'),
        );
      },
      error: (error: unknown) => {
        this.envoi.set(false);
        this.toasts.erreur(messageErreur(error, 'La réservation n’a pas pu être enregistrée.'));
        // Places ou statut peut-être changés entre-temps : on rafraîchit la fiche.
        this.charger(String(seance.id), true);
      },
    });
  }
}
