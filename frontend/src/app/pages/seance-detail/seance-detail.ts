import { DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/auth/auth.service';
import { messageErreur } from '../../core/http';
import { NIVEAUX, SeanceDetail } from '../../core/models';
import { CatalogueService } from '../../core/services/catalogue.service';
import { CompteService } from '../../core/services/compte.service';
import { ToastService } from '../../core/services/toast.service';
import { AvisSectionComponent } from '../../shared/avis-section';
import { FavoriButtonComponent } from '../../shared/favori-button';
import { EtatVideComponent, EtoilesComponent, ImageSecoursDirective, LoaderComponent } from '../../shared/ui';

type Etat = 'chargement' | 'ok' | 'introuvable' | 'erreur';

/** Fiche d'une séance et réservation (US-V04, UC-08). */
@Component({
  selector: 'app-seance-detail',
  imports: [DatePipe, DecimalPipe, RouterLink, LoaderComponent, EtatVideComponent, EtoilesComponent, ImageSecoursDirective, FavoriButtonComponent, AvisSectionComponent],
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

  readonly etat = signal<Etat>('chargement');
  readonly seance = signal<SeanceDetail | null>(null);
  readonly envoi = signal(false);
  /** Réservation faite depuis cette page : lien vers « Mes réservations ». */
  readonly reservee = signal(false);

  readonly niveau = computed(() => (this.seance() ? NIVEAUX[this.seance()!.niveau] : ''));
  readonly complet = computed(() => (this.seance()?.placesDisponibles ?? 0) <= 0);
  readonly passee = computed(() => {
    const s = this.seance();
    return !!s && (s.statut === 'annulee' || new Date(s.dateDebut) <= new Date());
  });
  readonly duree = computed(() => {
    const s = this.seance();
    return s ? Math.round((new Date(s.dateFin).getTime() - new Date(s.dateDebut).getTime()) / 60000) : 0;
  });
  /** Espace de gestion selon le rôle de l'organisateur connecté. */
  readonly lienGestion = computed(() => {
    const role = this.auth.role();
    const espace = role === 'admin' ? 'admin' : role === 'gestionnaire' ? 'gestionnaire' : 'coach';
    return ['/', espace, 'seances', this.id()];
  });

  constructor() {
    effect(() => this.charger(this.id()));
  }

  charger(id: string, silencieux = false): void {
    if (!silencieux) this.etat.set('chargement');
    this.catalogue.seance(Number(id)).subscribe({
      next: seance => {
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
    if (!seance || this.envoi()) return;
    if (!this.auth.estConnecte()) {
      void this.router.navigate(['/connexion'], { queryParams: { returnUrl: this.router.url } });
      return;
    }

    this.envoi.set(true);
    this.compte.reserver(seance.id).subscribe({
      next: () => {
        this.envoi.set(false);
        this.reservee.set(true);
        this.seance.update(s => (s ? { ...s, placesDisponibles: Math.max(0, s.placesDisponibles - 1) } : s));
        this.toasts.succes('Réservation confirmée ! Un e-mail de confirmation vous a été envoyé.');
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
