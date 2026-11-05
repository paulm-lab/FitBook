import { Component, computed, effect, inject, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Params, Router } from '@angular/router';
import { Subject, catchError, debounceTime, map, of, startWith, switchMap } from 'rxjs';
import { messageErreur } from '../../core/http';
import {
  Coach,
  CriteresRecherche,
  NIVEAUX,
  Niveau,
  Page,
  Salle,
  SeanceResume,
} from '../../core/models';
import { CatalogueService } from '../../core/services/catalogue.service';
import { ToastService } from '../../core/services/toast.service';
import { CarteComponent, PointCarte } from '../../shared/carte';
import { CoachCardComponent } from '../../shared/coach-card';
import { SalleCardComponent } from '../../shared/salle-card';
import { SeanceCardComponent } from '../../shared/seance-card';
import { EtatVideComponent, LoaderComponent, PaginationComponent } from '../../shared/ui';

type TypeRecherche = 'seances' | 'salles' | 'coachs';

/** Paramètres d'URL transmis tels quels à /api/recherche. */
const CLES_API = [
  'type',
  'q',
  'ville',
  'activite',
  'activiteId',
  'du',
  'au',
  'prixMax',
  'niveau',
  'noteMin',
  'lat',
  'lng',
  'rayon',
  'tri',
  'page',
] as const;

/** Tris proposés selon le type de résultats. */
const TRIS: Record<TypeRecherche, { valeur: string; libelle: string }[]> = {
  seances: [
    { valeur: 'date', libelle: 'Date' },
    { valeur: 'prix', libelle: 'Prix croissant' },
    { valeur: 'note', libelle: 'Mieux notées' },
  ],
  salles: [
    { valeur: 'nom', libelle: 'Nom' },
    { valeur: 'note', libelle: 'Mieux notées' },
  ],
  coachs: [
    { valeur: 'nom', libelle: 'Nom' },
    { valeur: 'note', libelle: 'Mieux notés' },
    { valeur: 'prix', libelle: 'Tarif croissant' },
  ],
};

interface Resultats {
  chargement: boolean;
  page: Page<SeanceResume | Salle | Coach> | null;
  erreur: string;
}

/**
 * Recherche avancée (US-V01 à US-V03). Les critères vivent dans l'URL : un lien est
 * partageable, le bouton « précédent » du navigateur restaure la recherche, et chaque
 * modification relance la recherche sans recharger la page.
 */
@Component({
  selector: 'app-recherche',
  imports: [
    FormsModule,
    LoaderComponent,
    EtatVideComponent,
    PaginationComponent,
    SeanceCardComponent,
    SalleCardComponent,
    CoachCardComponent,
    CarteComponent,
  ],
  templateUrl: './recherche.html',
  styleUrl: './recherche.css',
})
export class RechercheComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly catalogue = inject(CatalogueService);
  private readonly toasts = inject(ToastService);

  readonly niveaux = Object.entries(NIVEAUX) as [Niveau, string][];
  readonly rayons = [2, 5, 10, 20, 50];
  readonly activites = toSignal(this.catalogue.activites().pipe(catchError(() => of([]))), {
    initialValue: [],
  });

  /** Paramètres de l'URL, source de vérité des filtres. */
  readonly params = toSignal(this.route.queryParams, { initialValue: {} as Params });
  readonly type = computed<TypeRecherche>(() => {
    const type = this.params()['type'];
    return type === 'salles' || type === 'coachs' ? type : 'seances';
  });
  readonly vue = computed<'liste' | 'carte'>(() =>
    this.params()['vue'] === 'carte' && this.type() !== 'coachs' ? 'carte' : 'liste',
  );
  readonly tris = computed(() => {
    const tris = [...TRIS[this.type()]];
    if (this.position() && this.type() !== 'coachs')
      tris.push({ valeur: 'distance', libelle: 'Distance' });
    return tris;
  });
  readonly position = computed(() => {
    const lat = Number(this.params()['lat']);
    const lng = Number(this.params()['lng']);
    return this.params()['lat'] && Number.isFinite(lat) && Number.isFinite(lng)
      ? { lat, lng }
      : null;
  });

  // Champs texte : saisie locale, URL mise à jour après une courte pause (debounce).
  readonly q = signal('');
  readonly ville = signal('');
  private readonly saisie = new Subject<void>();
  readonly localisation = signal(false);

  readonly criteres = computed<CriteresRecherche>(() => {
    const p = this.params();
    const criteres: Record<string, unknown> = {};
    for (const cle of CLES_API) {
      if (p[cle] !== undefined && p[cle] !== '') criteres[cle] = p[cle];
    }
    // Vue carte : plus de résultats d'un coup pour remplir la carte.
    return {
      ...criteres,
      type: this.type(),
      parPage: this.vue() === 'carte' ? 50 : 12,
    } as CriteresRecherche;
  });

  private readonly resultats = toSignal(
    toObservable(this.criteres).pipe(
      switchMap((criteres) =>
        this.catalogue.rechercher(criteres).pipe(
          map((page): Resultats => ({ chargement: false, page, erreur: '' })),
          catchError((error) =>
            of<Resultats>({
              chargement: false,
              page: null,
              erreur: messageErreur(error, 'La recherche n’a pas pu aboutir.'),
            }),
          ),
          startWith<Resultats>({ chargement: true, page: null, erreur: '' }),
        ),
      ),
    ),
    { initialValue: { chargement: true, page: null, erreur: '' } as Resultats },
  );

  readonly chargement = computed(() => this.resultats().chargement);
  readonly erreur = computed(() => this.resultats().erreur);
  readonly page = computed(() => this.resultats().page);
  readonly seances = computed(() =>
    this.type() === 'seances' ? ((this.page()?.items ?? []) as SeanceResume[]) : [],
  );
  readonly salles = computed(() =>
    this.type() === 'salles' ? ((this.page()?.items ?? []) as Salle[]) : [],
  );
  readonly coachs = computed(() =>
    this.type() === 'coachs' ? ((this.page()?.items ?? []) as Coach[]) : [],
  );

  /** Filtres actifs affichés en chips, retirables un à un. */
  readonly filtresActifs = computed(() => {
    const p = this.params();
    const chips: { cle: string[]; libelle: string }[] = [];
    if (p['q']) chips.push({ cle: ['q'], libelle: `« ${p['q']} »` });
    if (p['ville']) chips.push({ cle: ['ville'], libelle: `Lieu : ${p['ville']}` });
    if (p['activite']) chips.push({ cle: ['activite'], libelle: `Activité : ${p['activite']}` });
    if (p['activiteId']) {
      const nom =
        this.activites().find((a) => a.id === Number(p['activiteId']))?.nom ??
        `n° ${p['activiteId']}`;
      chips.push({ cle: ['activiteId'], libelle: `Activité : ${nom}` });
    }
    if (p['du']) chips.push({ cle: ['du'], libelle: `À partir du ${this.dateCourte(p['du'])}` });
    if (p['au']) chips.push({ cle: ['au'], libelle: `Jusqu’au ${this.dateCourte(p['au'])}` });
    if (p['prixMax'])
      chips.push({
        cle: ['prixMax'],
        libelle: `${this.type() === 'coachs' ? 'Tarif' : 'Prix'} ≤ ${p['prixMax']} €`,
      });
    if (p['niveau'])
      chips.push({ cle: ['niveau'], libelle: NIVEAUX[p['niveau'] as Niveau] ?? p['niveau'] });
    if (p['noteMin']) chips.push({ cle: ['noteMin'], libelle: `Note ≥ ${p['noteMin']} ★` });
    if (this.position())
      chips.push({
        cle: ['lat', 'lng', 'rayon'],
        libelle: `Autour de moi${p['rayon'] ? ' · ' + p['rayon'] + ' km' : ''}`,
      });
    return chips;
  });

  /** Marqueurs : les salles des résultats (une par salle pour les séances). */
  readonly points = computed<PointCarte[]>(() => {
    if (this.type() === 'salles') {
      return this.salles()
        .filter((s) => s.latitude && s.longitude)
        .map((s) => ({
          id: s.id,
          latitude: +s.latitude!,
          longitude: +s.longitude!,
          titre: s.nom,
          sousTitre: s.adresse,
          lien: `/salles/${s.id}`,
        }));
    }
    const parSalle = new Map<number, PointCarte & { n: number }>();
    for (const s of this.seances()) {
      const salle = s.salle;
      if (!salle?.latitude || !salle.longitude) continue;
      const point = parSalle.get(salle.id);
      if (point) {
        point.n++;
        point.sousTitre = `${point.n} séances trouvées`;
        point.lien = `/salles/${salle.id}`;
      } else {
        parSalle.set(salle.id, {
          id: salle.id,
          latitude: +salle.latitude,
          longitude: +salle.longitude,
          titre: salle.nom,
          sousTitre: s.titre,
          lien: `/seances/${s.id}`,
          n: 1,
        });
      }
    }
    return [...parSalle.values()];
  });

  constructor() {
    // L'URL change (navigation, réinitialisation) : les champs texte suivent.
    effect(() => {
      const p = this.params();
      this.q.set(p['q'] ?? '');
      this.ville.set(p['ville'] ?? '');
    });
    this.saisie
      .pipe(debounceTime(400))
      .subscribe(() =>
        this.appliquer({ q: this.q().trim() || null, ville: this.ville().trim() || null }, true),
      );
  }

  saisir(champ: 'q' | 'ville', valeur: string): void {
    this[champ].set(valeur);
    this.saisie.next();
  }

  changerType(type: TypeRecherche): void {
    if (type === this.type()) return;
    // Les filtres propres aux séances n'ont pas de sens pour les salles et les coachs.
    this.appliquer({
      type: type === 'seances' ? null : type,
      tri: null,
      niveau: null,
      du: type === 'seances' ? (this.params()['du'] ?? null) : null,
      au: type === 'seances' ? (this.params()['au'] ?? null) : null,
      prixMax: type === 'salles' ? null : (this.params()['prixMax'] ?? null),
      vue: type === 'coachs' ? null : (this.params()['vue'] ?? null),
    });
  }

  changer(cle: string, valeur: string | number | null): void {
    this.appliquer({ [cle]: valeur === '' ? null : valeur });
  }

  /** Activité choisie dans la liste : un identifiant exact remplace le nom saisi. */
  changerActivite(id: string): void {
    this.appliquer({ activiteId: id || null, activite: null });
  }

  retirer(cles: string[]): void {
    const changements: Params = Object.fromEntries(cles.map((c) => [c, null]));
    if (cles.includes('lat') && this.params()['tri'] === 'distance') changements['tri'] = null;
    this.appliquer(changements);
  }

  reinitialiser(): void {
    void this.router.navigate([], {
      queryParams: {
        type: this.type() === 'seances' ? null : this.type(),
        vue: this.params()['vue'] ?? null,
      },
    });
  }

  changerPage(page: number): void {
    this.appliquer({ page: page > 1 ? page : null }, false, false);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  basculerVue(vue: 'liste' | 'carte'): void {
    void this.router.navigate([], {
      queryParams: { vue: vue === 'carte' ? 'carte' : null, page: null },
      queryParamsHandling: 'merge',
    });
  }

  /** « Autour de moi » : position du navigateur, rayon de 10 km et tri par distance. */
  autourDeMoi(): void {
    if (!('geolocation' in navigator)) {
      this.toasts.erreur('Votre navigateur ne permet pas la géolocalisation.');
      return;
    }
    this.localisation.set(true);
    navigator.geolocation.getCurrentPosition(
      ({ coords }) => {
        this.localisation.set(false);
        this.appliquer({
          lat: coords.latitude.toFixed(5),
          lng: coords.longitude.toFixed(5),
          rayon: this.params()['rayon'] ?? 10,
          tri: this.type() === 'coachs' ? null : 'distance',
        });
      },
      (error) => {
        this.localisation.set(false);
        this.toasts.erreur(
          error.code === error.PERMISSION_DENIED
            ? 'Position refusée : autorisez la géolocalisation pour chercher autour de vous.'
            : 'Votre position n’a pas pu être déterminée.',
        );
      },
      { enableHighAccuracy: false, timeout: 10000, maximumAge: 300000 },
    );
  }

  ouvrir(lien: string): void {
    void this.router.navigateByUrl(lien);
  }

  /** Fusionne les changements dans l'URL ; un filtre modifié ramène à la page 1. */
  private appliquer(changements: Params, remplacer = false, pageUn = true): void {
    void this.router.navigate([], {
      queryParams: { ...changements, ...(pageUn ? { page: null } : {}) },
      queryParamsHandling: 'merge',
      replaceUrl: remplacer,
    });
  }

  private dateCourte(iso: string): string {
    const [annee, mois, jour] = iso.split('-');
    return annee && mois && jour ? `${jour}/${mois}/${annee}` : iso;
  }
}
