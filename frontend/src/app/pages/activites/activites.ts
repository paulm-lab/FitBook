import { Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, map, of, startWith } from 'rxjs';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';
import { Activite } from '../../core/models';
import { CatalogueService } from '../../core/services/catalogue.service';
import { EtatVideComponent, ImageSecoursDirective, LoaderComponent } from '../../shared/ui';

interface EtatActivites {
  chargement: boolean;
  activites: Activite[];
  erreur: boolean;
}

/** Catalogue des disciplines, filtrable par catégorie ; chaque carte ouvre la recherche. */
@Component({
  selector: 'app-activites',
  imports: [FormsModule, RouterLink, VisualShowcaseComponent, LoaderComponent, EtatVideComponent, ImageSecoursDirective],
  templateUrl: './activites.html',
  styleUrl: './activites.css',
})
export class ActivitesComponent {
  private readonly catalogue = inject(CatalogueService);

  private readonly etat = toSignal(
    this.catalogue.activites().pipe(
      map((activites): EtatActivites => ({ chargement: false, activites, erreur: false })),
      catchError(() => of<EtatActivites>({ chargement: false, activites: [], erreur: true })),
      startWith<EtatActivites>({ chargement: true, activites: [], erreur: false }),
    ),
    { initialValue: { chargement: true, activites: [], erreur: false } as EtatActivites },
  );

  readonly chargement = computed(() => this.etat().chargement);
  readonly erreur = computed(() => this.etat().erreur);
  readonly categorie = signal('');
  readonly texte = signal('');

  readonly categories = computed(() => [...new Set(this.etat().activites.map(a => a.categorie))].sort((a, b) => a.localeCompare(b, 'fr')));

  readonly activites = computed(() => {
    const terme = this.texte().trim().toLocaleLowerCase('fr');
    return this.etat().activites.filter(a =>
      (!this.categorie() || a.categorie === this.categorie())
      && (!terme || `${a.nom} ${a.description ?? ''} ${a.categorie}`.toLocaleLowerCase('fr').includes(terme)));
  });
}
