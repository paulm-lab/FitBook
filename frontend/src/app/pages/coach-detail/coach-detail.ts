import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CoachDetail } from '../../core/models';
import { CatalogueService } from '../../core/services/catalogue.service';
import { AvisSectionComponent } from '../../shared/avis-section';
import { FavoriButtonComponent } from '../../shared/favori-button';
import { SeanceCardComponent } from '../../shared/seance-card';
import {
  EtatVideComponent,
  EtoilesComponent,
  ImageSecoursDirective,
  LoaderComponent,
} from '../../shared/ui';
import { DisponibilitesListeComponent } from '../salle-detail/disponibilites-liste';
import { I18N } from '../../core/i18n/pipes';

type Etat = 'chargement' | 'ok' | 'introuvable' | 'erreur';

/** Fiche publique d'un coach (US-V04, US-C01). */
@Component({
  selector: 'app-coach-detail',
  imports: [
    ...I18N,
    RouterLink,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    ImageSecoursDirective,
    FavoriButtonComponent,
    SeanceCardComponent,
    AvisSectionComponent,
    DisponibilitesListeComponent,
  ],
  templateUrl: './coach-detail.html',
  styleUrl: './coach-detail.css',
})
export class CoachDetailComponent {
  readonly id = input.required<string>();

  private readonly catalogue = inject(CatalogueService);

  readonly etat = signal<Etat>('chargement');
  readonly coach = signal<CoachDetail | null>(null);
  readonly initiales = computed(() =>
    `${this.coach()?.prenom?.[0] ?? ''}${this.coach()?.nom?.[0] ?? ''}`.toUpperCase(),
  );

  constructor() {
    effect(() => this.charger(this.id()));
  }

  charger(id: string, silencieux = false): void {
    if (!silencieux) this.etat.set('chargement');
    this.catalogue.coach(Number(id)).subscribe({
      next: (coach) => {
        this.coach.set(coach);
        this.etat.set('ok');
      },
      error: (error: unknown) => {
        this.etat.set(
          error instanceof HttpErrorResponse && error.status === 404 ? 'introuvable' : 'erreur',
        );
      },
    });
  }
}
