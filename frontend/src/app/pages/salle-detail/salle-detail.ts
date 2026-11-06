import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Subscription } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { SalleDetail } from '../../core/models';
import { CatalogueService } from '../../core/services/catalogue.service';
import { AvisSectionComponent } from '../../shared/avis-section';
import { CarteComponent, PointCarte } from '../../shared/carte';
import { FavoriButtonComponent } from '../../shared/favori-button';
import { SeanceCardComponent } from '../../shared/seance-card';
import {
  EtatVideComponent,
  EtoilesComponent,
  ImageSecoursDirective,
  LoaderComponent,
} from '../../shared/ui';
import { DisponibilitesListeComponent } from './disponibilites-liste';

import { I18N } from '../../core/i18n/pipes';

type Etat = 'chargement' | 'ok' | 'introuvable' | 'erreur';

const JOURS_OUVERTURE = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

/** Fiche publique d'une salle (US-V04). */
@Component({
  selector: 'app-salle-detail',
  imports: [
    ...I18N,
    RouterLink,
    LoaderComponent,
    EtatVideComponent,
    EtoilesComponent,
    ImageSecoursDirective,
    FavoriButtonComponent,
    SeanceCardComponent,
    CarteComponent,
    AvisSectionComponent,
    DisponibilitesListeComponent,
  ],
  templateUrl: './salle-detail.html',
  styleUrl: './salle-detail.css',
})
export class SalleDetailComponent {
  readonly id = input.required<string>();

  readonly auth = inject(AuthService);
  private readonly catalogue = inject(CatalogueService);

  readonly etat = signal<Etat>('chargement');
  readonly salle = signal<SalleDetail | null>(null);
  readonly photo = signal(0);

  readonly photos = computed(() => {
    const s = this.salle();
    return s?.photos.length ? s.photos : ['/assets/fitbook-media-fallback.svg'];
  });
  readonly horaires = computed(() =>
    JOURS_OUVERTURE.map((jour) => ({ jour, heures: this.salle()?.horaires?.[jour] ?? null })),
  );
  readonly point = computed<PointCarte[]>(() => {
    const s = this.salle();
    return s?.latitude && s.longitude
      ? [
          {
            id: s.id,
            latitude: +s.latitude,
            longitude: +s.longitude,
            titre: s.nom,
            sousTitre: s.adresse,
            lien: `/salles/${s.id}`,
          },
        ]
      : [];
  });
  readonly lienModifier = computed(() => [
    '/',
    this.auth.role() === 'admin' ? 'admin' : 'gestionnaire',
    'salles',
    this.id(),
  ]);
  readonly aujourdhui = JOURS_OUVERTURE[(new Date().getDay() + 6) % 7];

  private requete?: Subscription;

  constructor() {
    effect(() => this.charger(this.id()));
  }

  charger(id: string, silencieux = false): void {
    if (!silencieux) {
      this.etat.set('chargement');
      this.photo.set(0);
    }
    // Autre identifiant avant la réponse : la requête précédente est annulée et ne peut plus
    // écraser la fiche affichée.
    this.requete?.unsubscribe();
    this.requete = this.catalogue.salle(Number(id)).subscribe({
      next: (salle) => {
        this.salle.set(salle);
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
