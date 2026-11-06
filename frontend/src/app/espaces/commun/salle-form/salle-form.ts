import { Component, computed, effect, inject, input, signal } from '@angular/core';
import {
  FormBuilder,
  FormControl,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { erreursChamps, messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { UtilisateurAdmin } from '../../../core/models';
import { GestionService, SalleSaisie } from '../../../core/services/gestion.service';
import { CatalogueService } from '../../../core/services/catalogue.service';
import { ToastService } from '../../../core/services/toast.service';
import { ConfirmService, ImageSecoursDirective, LoaderComponent } from '../../../shared/ui';
import { espaceCourant } from '../espace';

const JOURS_SEMAINE = [
  'lundi',
  'mardi',
  'mercredi',
  'jeudi',
  'vendredi',
  'samedi',
  'dimanche',
] as const;
const MAX_PHOTOS = 10;

type Horaire = FormGroup<{
  ferme: FormControl<boolean>;
  ouverture: FormControl<string>;
  fermeture: FormControl<string>;
}>;

/** Création et modification d'une salle (US-G01, US-A03). */
@Component({
  selector: 'app-salle-form',
  imports: [ReactiveFormsModule, RouterLink, LoaderComponent, ImageSecoursDirective, ...I18N],
  styles: `
    .hours {
      display: grid;
      gap: 8px;
    }
    .hours-row {
      display: grid;
      grid-template-columns: 110px 100px 1fr 1fr;
      align-items: center;
      gap: 10px;
    }
    .hours-row input[type='time'] {
      min-height: 40px;
      padding: 6px 10px;
      border: 1px solid var(--border);
      border-radius: var(--radius-field);
      background: var(--surface);
      color: var(--ink);
    }
    .photos {
      display: grid;
      gap: 10px;
      grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    }
    .photo {
      position: relative;
      aspect-ratio: 4 / 3;
      overflow: hidden;
      border-radius: 12px;
      background: var(--surface-alt);
    }
    .photo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .photo button {
      position: absolute;
      top: 6px;
      right: 6px;
    }
    @media (max-width: 600px) {
      .hours-row {
        grid-template-columns: 1fr 1fr;
      }
    }
  `,
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ (id() ? 'Modification' : 'Nouvelle salle') | t }}</span>
        <h1>{{ (id() ? 'Modifier la salle' : 'Créer une salle') | t }}</h1>
      </div>
      <a class="btn btn--ghost" [routerLink]="retour">{{ '← Retour' | t }}</a>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else {
      @if (erreur()) {
        <p class="alert alert--error" role="alert">{{ erreur() }}</p>
      }
      <form class="stack" [formGroup]="form" (ngSubmit)="enregistrer()" novalidate>
        <section class="panel form">
          <h2>{{ 'Informations' | t }}</h2>
          <div class="field">
            <label for="g-nom">{{ 'Nom' | t }}</label>
            <input
              id="g-nom"
              formControlName="nom"
              maxlength="100"
              [attr.aria-invalid]="!!message('nom')"
              aria-describedby="g-nom-err"
            />
            @if (message('nom'); as m) {
              <small class="field-error" id="g-nom-err">{{ m }}</small>
            }
          </div>
          <div class="field">
            <label for="g-desc">{{ 'Description' | t }}</label>
            <textarea id="g-desc" formControlName="description" maxlength="2000"></textarea>
            @if (message('description'); as m) {
              <small class="field-error">{{ m }}</small>
            }
          </div>
          @if (espace === 'admin') {
            <div class="field">
              <label for="g-gest">{{ 'Gestionnaire' | t }}</label>
              <select
                id="g-gest"
                formControlName="gestionnaireId"
                [attr.aria-invalid]="!!message('gestionnaireId')"
              >
                <option [ngValue]="null">{{ 'Choisir…' | t }}</option>
                @for (u of gestionnaires(); track u.id) {
                  <option [ngValue]="u.id">{{ u.prenom }} {{ u.nom }} — {{ u.email }}</option>
                }
                <!-- Gestionnaire actuel absent de la liste (rôle changé…) : il reste affiché. -->
                @if (gestionnaireAbsent(); as id) {
                  <option [ngValue]="id">
                    {{ 'Gestionnaire actuel (n° {id})' | t: { id: id } }}
                  </option>
                }
              </select>
              @if (message('gestionnaireId'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
          }
        </section>

        <section class="panel form">
          <h2>{{ 'Adresse et contact' | t }}</h2>
          <div class="field">
            <label for="g-adresse">{{ 'Adresse' | t }}</label>
            <input
              id="g-adresse"
              formControlName="adresse"
              autocomplete="street-address"
              [attr.aria-invalid]="!!message('adresse')"
            />
            @if (message('adresse'); as m) {
              <small class="field-error">{{ m }}</small>
            }
          </div>
          <div class="form-row">
            <div class="field">
              <label for="g-cp">{{ 'Code postal' | t }}</label>
              <input
                id="g-cp"
                formControlName="codePostal"
                inputmode="numeric"
                maxlength="5"
                [attr.aria-invalid]="!!message('codePostal')"
              />
              @if (message('codePostal'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
            <div class="field">
              <label for="g-ville">{{ 'Commune' | t }}</label>
              <input
                id="g-ville"
                formControlName="ville"
                [attr.aria-invalid]="!!message('ville')"
              />
              @if (message('ville'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
          </div>
          <div class="form-row">
            <div class="field">
              <label for="g-lat"
                >{{ 'Latitude' | t }}
                <span class="field-hint">{{ '(facultative, ex. 50.8466)' | t }}</span></label
              >
              <input
                id="g-lat"
                type="number"
                step="0.000001"
                formControlName="latitude"
                [attr.aria-invalid]="!!message('latitude')"
              />
              @if (message('latitude'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
            <div class="field">
              <label for="g-lng"
                >{{ 'Longitude' | t }}
                <span class="field-hint">{{ '(facultative, ex. 4.3528)' | t }}</span></label
              >
              <input
                id="g-lng"
                type="number"
                step="0.000001"
                formControlName="longitude"
                [attr.aria-invalid]="!!message('longitude')"
              />
              @if (message('longitude'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
          </div>
          <p class="field-hint" style="margin:0">
            {{
              'Les coordonnées placent la salle sur la carte et permettent la recherche par distance.'
                | t
            }}
          </p>
          <div class="form-row">
            <div class="field">
              <label for="g-tel">{{ 'Téléphone' | t }}</label>
              <input
                id="g-tel"
                type="tel"
                formControlName="telephone"
                autocomplete="tel"
                [attr.aria-invalid]="!!message('telephone')"
              />
              @if (message('telephone'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
            <div class="field">
              <label for="g-mail">{{ 'E-mail' | t }}</label>
              <input
                id="g-mail"
                type="email"
                formControlName="email"
                [attr.aria-invalid]="!!message('email')"
              />
              @if (message('email'); as m) {
                <small class="field-error">{{ m }}</small>
              }
            </div>
          </div>
        </section>

        <section class="panel">
          <h2>{{ 'Horaires d’ouverture' | t }}</h2>
          <div class="hours" formArrayName="horaires">
            @for (jour of jours; track jour; let i = $index) {
              <div class="hours-row" [formGroupName]="i">
                <strong style="text-transform:capitalize">{{ jour | t }}</strong>
                <label class="check"
                  ><input type="checkbox" formControlName="ferme" /> {{ 'Fermé' | t }}</label
                >
                @if (!horaires.at(i).controls.ferme.value) {
                  <label
                    ><span class="sr-only">{{
                      'Ouverture le {jour}' | t: { jour: (jour | t) }
                    }}</span
                    ><input type="time" formControlName="ouverture"
                  /></label>
                  <label
                    ><span class="sr-only">{{
                      'Fermeture le {jour}' | t: { jour: (jour | t) }
                    }}</span
                    ><input type="time" formControlName="fermeture"
                  /></label>
                }
              </div>
            }
          </div>
          @if (erreurHoraires()) {
            <small class="field-error" role="alert">{{ erreurHoraires() }}</small>
          }
        </section>

        <section class="panel">
          <h2>
            {{ 'Photos' | t }}
            <span class="muted" style="font-size:14px"
              >({{ photos().length }}/{{ maxPhotos }})</span
            >
          </h2>
          <div class="row" style="margin-bottom:12px">
            <label class="sr-only" for="g-photo">{{ 'Adresse web de la photo' | t }}</label>
            <input
              id="g-photo"
              class="input"
              style="flex:1;min-width:220px"
              type="url"
              placeholder="https://…"
              #photo
              (keydown.enter)="$event.preventDefault(); ajouterPhoto(photo)"
            />
            <button
              type="button"
              class="btn btn--ghost"
              [disabled]="photos().length >= maxPhotos"
              (click)="ajouterPhoto(photo)"
            >
              {{ 'Ajouter' | t }}
            </button>
          </div>
          @if (message('photos'); as m) {
            <small class="field-error">{{ m }}</small>
          }
          <div class="photos">
            @for (url of photos(); track url; let i = $index) {
              <figure class="photo">
                <img [src]="url" [alt]="'Photo {n}' | t: { n: i + 1 }" loading="lazy" appSecours />
                <button
                  type="button"
                  class="btn btn--sm btn--danger"
                  (click)="retirerPhoto(i)"
                  [attr.aria-label]="'Retirer la photo {n}' | t: { n: i + 1 }"
                >
                  ×
                </button>
              </figure>
            }
          </div>
        </section>

        <div class="row">
          <button type="submit" class="btn" [disabled]="envoi()" [attr.aria-busy]="envoi()">
            {{ (envoi() ? 'Enregistrement…' : id() ? 'Enregistrer' : 'Créer la salle') | t }}
          </button>
          <a class="btn btn--ghost" [routerLink]="retour">{{ 'Annuler' | t }}</a>
          @if (id()) {
            <button
              type="button"
              class="btn btn--danger"
              (click)="supprimer()"
              style="margin-left:auto"
            >
              {{ 'Supprimer la salle' | t }}
            </button>
          }
        </div>
      </form>
    }
  `,
})
export class SalleFormComponent {
  readonly id = input<string>();

  private readonly fb = inject(FormBuilder);
  private readonly gestion = inject(GestionService);
  private readonly catalogue = inject(CatalogueService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly router = inject(Router);
  private readonly langue = inject(LangueService);

  readonly espace = espaceCourant();
  readonly retour = `/${this.espace}/salles`;
  readonly jours = JOURS_SEMAINE;
  readonly maxPhotos = MAX_PHOTOS;
  readonly chargement = signal(false);
  readonly envoi = signal(false);
  readonly erreur = signal('');
  readonly erreurHoraires = signal('');
  readonly serveur = signal<Record<string, string>>({});
  readonly soumis = signal(false);
  readonly photos = signal<string[]>([]);
  readonly gestionnaires = signal<UtilisateurAdmin[]>([]);
  /** Gestionnaire enregistré sur la salle chargée. */
  private readonly gestionnaireActuel = signal<number | null>(null);
  /** Identifiant du gestionnaire actuel s'il ne figure pas dans la liste des gestionnaires. */
  readonly gestionnaireAbsent = computed(() => {
    const id = this.gestionnaireActuel();
    return id !== null && !this.gestionnaires().some((u) => u.id === id) ? id : null;
  });

  readonly horaires = this.fb.array<Horaire>(
    JOURS_SEMAINE.map((jour) =>
      this.fb.group({
        ferme: this.fb.nonNullable.control(jour === 'dimanche'),
        ouverture: this.fb.nonNullable.control('07:00'),
        fermeture: this.fb.nonNullable.control('22:00'),
      }),
    ),
  );

  readonly form = this.fb.group({
    nom: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(100)]),
    description: this.fb.nonNullable.control(''),
    adresse: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(255)]),
    ville: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(100)]),
    codePostal: this.fb.nonNullable.control('', [
      Validators.required,
      Validators.pattern(/^\d{4,5}$/),
    ]),
    latitude: this.fb.control<number | null>(null, [Validators.min(-90), Validators.max(90)]),
    longitude: this.fb.control<number | null>(null, [Validators.min(-180), Validators.max(180)]),
    telephone: this.fb.nonNullable.control('', Validators.pattern(/^\+?[0-9\s().-]{7,20}$/)),
    email: this.fb.nonNullable.control('', Validators.email),
    gestionnaireId: this.fb.control<number | null>(null),
    horaires: this.horaires,
  });

  constructor() {
    if (this.espace === 'admin') {
      this.chargerGestionnaires(1);
    }
    effect(() => {
      const id = Number(this.id());
      if (id > 0) this.charger(id);
    });
  }

  message(champ: string): string | null {
    if (this.serveur()[champ]) return this.serveur()[champ];
    const c = this.form.get(champ);
    if (!c || c.valid || !(c.touched || this.soumis())) return null;
    const t = (texte: string) => this.langue.t(texte);
    if (c.hasError('required')) return t('Ce champ est obligatoire.');
    if (c.hasError('pattern'))
      return champ === 'codePostal'
        ? t('Le code postal contient 4 ou 5 chiffres.')
        : t('Saisissez un numéro valide.');
    if (c.hasError('email')) return t('Saisissez une adresse e-mail valide.');
    if (c.hasError('min') || c.hasError('max')) return t('Coordonnée hors limites.');
    return t('Valeur invalide.');
  }

  ajouterPhoto(champ: HTMLInputElement): void {
    const url = champ.value.trim();
    if (!url) return;
    if (!/^https?:\/\/\S+\.\S+/.test(url)) {
      this.toasts.erreur(this.langue.t('Saisissez une adresse web valide (https://…).'));
      return;
    }
    if (this.photos().length >= MAX_PHOTOS || this.photos().includes(url)) return;
    this.photos.update((p) => [...p, url]);
    champ.value = '';
  }

  retirerPhoto(index: number): void {
    this.photos.update((p) => p.filter((_, i) => i !== index));
  }

  enregistrer(): void {
    this.soumis.set(true);
    this.serveur.set({});
    this.erreur.set('');
    this.erreurHoraires.set('');

    const horaires: Record<string, string> = {};
    for (const [i, jour] of JOURS_SEMAINE.entries()) {
      const h = this.horaires.at(i).getRawValue();
      if (h.ferme) continue;
      if (!h.ouverture || !h.fermeture || h.fermeture <= h.ouverture) {
        this.erreurHoraires.set(
          this.langue.t('Horaires du {jour} : l’heure de fermeture doit suivre l’ouverture.', {
            jour: this.langue.t(jour),
          }),
        );
        return;
      }
      horaires[jour] = `${h.ouverture}-${h.fermeture}`;
    }
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const data: SalleSaisie = {
      nom: v.nom.trim(),
      description: v.description.trim() || null,
      adresse: v.adresse.trim(),
      ville: v.ville.trim(),
      codePostal: v.codePostal.trim(),
      latitude: v.latitude,
      longitude: v.longitude,
      telephone: v.telephone.trim() || null,
      email: v.email.trim() || null,
      horaires,
      photos: this.photos(),
      gestionnaireId: this.espace === 'admin' ? v.gestionnaireId : null,
    };

    this.envoi.set(true);
    const id = Number(this.id());
    (id > 0 ? this.gestion.modifierSalle(id, data) : this.gestion.creerSalle(data)).subscribe({
      next: () => {
        this.toasts.succes(this.langue.t(id > 0 ? 'Salle enregistrée.' : 'Salle créée.'));
        void this.router.navigateByUrl(this.retour);
      },
      error: (error: unknown) => {
        this.envoi.set(false);
        const champs = erreursChamps(error);
        this.serveur.set(champs);
        if (champs['horaires']) this.erreurHoraires.set(champs['horaires']);
        this.erreur.set(
          Object.keys(champs).length
            ? this.langue.t('Certains champs sont invalides.')
            : messageErreur(error),
        );
      },
    });
  }

  async supprimer(): Promise<void> {
    const id = Number(this.id());
    const ok = await this.confirm.demander({
      titre: this.langue.t('Supprimer cette salle ?'),
      message: this.langue.t(
        'La salle sera définitivement supprimée. Une salle qui a des séances ne peut pas être supprimée.',
      ),
      confirmer: this.langue.t('Supprimer'),
      danger: true,
    });
    if (!ok) return;
    this.gestion.supprimerSalle(id).subscribe({
      next: () => {
        this.toasts.succes(this.langue.t('Salle supprimée.'));
        void this.router.navigateByUrl(this.retour);
      },
      error: (error) => this.toasts.erreur(messageErreur(error)),
    });
  }

  /** Tous les gestionnaires, page par page (50 au maximum par page côté API). */
  private chargerGestionnaires(page: number): void {
    this.gestion.utilisateurs({ role: 'gestionnaire', page, parPage: 50 }).subscribe({
      next: (p) => {
        this.gestionnaires.update((liste) => (page === 1 ? p.items : [...liste, ...p.items]));
        if (p.page < p.pages) this.chargerGestionnaires(p.page + 1);
      },
    });
  }

  private charger(id: number): void {
    this.chargement.set(true);
    this.catalogue.salle(id).subscribe({
      next: (s) => {
        // L'API renvoie « rue, code postal ville » : on retire ce suffixe (la rue peut contenir
        // une virgule).
        const suffixe = `, ${s.codePostal} ${s.ville}`;
        const adresse = s.adresse.endsWith(suffixe)
          ? s.adresse.slice(0, -suffixe.length)
          : s.adresse;
        this.form.patchValue({
          nom: s.nom,
          description: s.description ?? '',
          adresse: adresse.trim(),
          ville: s.ville,
          codePostal: s.codePostal,
          latitude: s.latitude !== null ? Number(s.latitude) : null,
          longitude: s.longitude !== null ? Number(s.longitude) : null,
          telephone: s.telephone ?? '',
          email: s.email ?? '',
          gestionnaireId: s.gestionnaireId,
        });
        for (const [i, jour] of JOURS_SEMAINE.entries()) {
          const plage = s.horaires?.[jour];
          const [ouverture, fermeture] = plage ? plage.split('-') : ['07:00', '22:00'];
          this.horaires.at(i).setValue({ ferme: !plage, ouverture, fermeture });
        }
        this.photos.set(s.photos ?? []);
        this.gestionnaireActuel.set(s.gestionnaireId);
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(messageErreur(error, this.langue.t('Salle introuvable.')));
        this.chargement.set(false);
      },
    });
  }
}
