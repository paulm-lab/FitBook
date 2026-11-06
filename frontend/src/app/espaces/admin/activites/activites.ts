import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { erreursChamps, messageErreur } from '../../../core/http';
import { Activite } from '../../../core/models';
import { CatalogueService } from '../../../core/services/catalogue.service';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import {
  ConfirmService,
  EtatVideComponent,
  ImageSecoursDirective,
  LoaderComponent,
} from '../../../shared/ui';

/** Catalogue des activités (US-A03) : ajout, modification, suppression. */
@Component({
  selector: 'app-activites-admin',
  imports: [
    ReactiveFormsModule,
    LoaderComponent,
    EtatVideComponent,
    ImageSecoursDirective,
    ...I18N,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Administration' | t }}</span>
        <h1>{{ 'Activités' | t }}</h1>
        <p>{{ 'Les disciplines proposées sur FitBook.' | t }}</p>
      </div>
      @if (!formOuvert()) {
        <button type="button" class="btn" (click)="ouvrir(null)">
          {{ '+ Nouvelle activité' | t }}
        </button>
      }
    </header>

    @if (formOuvert()) {
      <section class="panel edit" aria-labelledby="form-titre">
        <h2 id="form-titre">
          {{
            edition()
              ? ('Modifier « {nom} »' | t: { nom: edition()!.nom })
              : ('Nouvelle activité' | t)
          }}
        </h2>
        <form class="form" [formGroup]="form" (ngSubmit)="enregistrer()" novalidate>
          <div class="form-row">
            <label class="field">
              <span>{{ 'Nom' | t }}</span>
              <input
                formControlName="nom"
                maxlength="50"
                [attr.aria-invalid]="!!erreur('nom')"
                [attr.aria-describedby]="erreur('nom') ? 'err-nom' : null"
              />
              @if (erreur('nom')) {
                <small class="field-error" id="err-nom" role="alert">{{ erreur('nom') }}</small>
              }
            </label>
            <label class="field">
              <span>{{ 'Catégorie' | t }}</span>
              <input
                formControlName="categorie"
                maxlength="50"
                [placeholder]="'Bien-être, Cardio…' | t"
                [attr.aria-invalid]="!!erreur('categorie')"
                [attr.aria-describedby]="erreur('categorie') ? 'err-cat' : null"
              />
              @if (erreur('categorie')) {
                <small class="field-error" id="err-cat" role="alert">{{
                  erreur('categorie')
                }}</small>
              }
            </label>
          </div>
          <label class="field">
            <span>{{ 'Description' | t }}</span>
            <textarea
              formControlName="description"
              maxlength="2000"
              [attr.aria-invalid]="!!erreur('description')"
            ></textarea>
            @if (erreur('description')) {
              <small class="field-error" role="alert">{{ erreur('description') }}</small>
            }
          </label>
          <div class="image-row">
            <label class="field">
              <span>{{ 'Adresse de l’image (URL)' | t }}</span>
              <input
                type="url"
                formControlName="image"
                placeholder="https://…"
                [attr.aria-invalid]="!!erreur('image')"
                [attr.aria-describedby]="erreur('image') ? 'err-img' : null"
              />
              @if (erreur('image')) {
                <small class="field-error" id="err-img" role="alert">{{ erreur('image') }}</small>
              }
            </label>
            @if (form.controls.image.value) {
              <img
                class="preview"
                [src]="form.controls.image.value"
                [alt]="'Aperçu de l’image' | t"
                appSecours
              />
            }
          </div>
          <div class="row">
            <button type="submit" class="btn" [disabled]="envoi()" [attr.aria-busy]="envoi()">
              {{ (envoi() ? 'Enregistrement…' : 'Enregistrer') | t }}
            </button>
            <button type="button" class="btn btn--ghost" (click)="fermer()">
              {{ 'Annuler' | t }}
            </button>
          </div>
        </form>
      </section>
    }

    @if (chargementErreur()) {
      <p class="alert alert--error" role="alert">{{ chargementErreur() }}</p>
    } @else if (!activites()) {
      <app-loader />
    } @else if (activites()!.length === 0) {
      <app-etat-vide
        [titre]="'Aucune activité' | t"
        [message]="'Ajoutez la première discipline de la plateforme.' | t"
      />
    } @else {
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">
                <span class="sr-only">{{ 'Image' | t }}</span>
              </th>
              <th scope="col">{{ 'Nom' | t }}</th>
              <th scope="col">{{ 'Catégorie' | t }}</th>
              <th scope="col">{{ 'Description' | t }}</th>
              <th scope="col">
                <span class="sr-only">{{ 'Actions' | t }}</span>
              </th>
            </tr>
          </thead>
          <tbody>
            @for (a of activites(); track a.id) {
              <tr>
                <td>
                  <img
                    class="thumb"
                    [src]="a.image || '/assets/fitbook-media-fallback.svg'"
                    alt=""
                    appSecours
                  />
                </td>
                <td>
                  <strong>{{ a.nom }}</strong>
                </td>
                <td>
                  <span class="badge">{{ a.categorie }}</span>
                </td>
                <td class="desc">{{ court(a.description) }}</td>
                <td>
                  <div class="actions">
                    <button type="button" class="btn btn--sm btn--ghost" (click)="ouvrir(a)">
                      {{ 'Modifier' | t }}
                    </button>
                    <button type="button" class="btn btn--sm btn--danger" (click)="supprimer(a)">
                      {{ 'Supprimer' | t }}
                    </button>
                  </div>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
  styles: `
    .edit {
      margin-bottom: 24px;
    }
    .image-row {
      display: grid;
      grid-template-columns: 1fr 160px;
      align-items: end;
      gap: 14px;
    }
    .preview {
      width: 160px;
      height: 100px;
      border-radius: 10px;
      object-fit: cover;
      background: var(--surface-alt);
    }
    .thumb {
      width: 64px;
      height: 44px;
      border-radius: 8px;
      object-fit: cover;
      background: var(--surface-alt);
    }
    .desc {
      max-width: 360px;
      color: var(--text);
    }
    @media (max-width: 600px) {
      .image-row {
        grid-template-columns: 1fr;
      }
    }
  `,
})
export class ActivitesAdminComponent {
  private readonly catalogue = inject(CatalogueService);
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly langue = inject(LangueService);

  readonly activites = signal<Activite[] | null>(null);
  readonly chargementErreur = signal('');
  readonly formOuvert = signal(false);
  readonly edition = signal<Activite | null>(null);
  readonly envoi = signal(false);
  readonly erreurs = signal<Record<string, string>>({});

  readonly form = inject(FormBuilder).nonNullable.group({
    nom: ['', [Validators.required, Validators.maxLength(50)]],
    categorie: ['', [Validators.required, Validators.maxLength(50)]],
    description: ['', Validators.maxLength(2000)],
    image: [''],
  });

  constructor() {
    this.charger();
  }

  court(texte: string | null | undefined): string {
    if (!texte) return '—';
    return texte.length > 90 ? texte.slice(0, 90) + '…' : texte;
  }

  /** Erreur d'un champ : serveur (422) ou validation locale après envoi. */
  erreur(champ: 'nom' | 'categorie' | 'description' | 'image'): string {
    const serveur = this.erreurs()[champ];
    if (serveur) return serveur;
    const control = this.form.controls[champ];
    if (!control.touched || control.valid) return '';
    if (control.hasError('required')) return this.langue.t('Ce champ est obligatoire.');
    if (control.hasError('maxlength')) return this.langue.t('Ce texte est trop long.');
    return '';
  }

  ouvrir(activite: Activite | null): void {
    this.edition.set(activite);
    this.erreurs.set({});
    this.form.reset({
      nom: activite?.nom ?? '',
      categorie: activite?.categorie ?? '',
      description: activite?.description ?? '',
      image: activite?.image ?? '',
    });
    this.formOuvert.set(true);
  }

  fermer(): void {
    this.formOuvert.set(false);
    this.edition.set(null);
  }

  enregistrer(): void {
    this.erreurs.set({});
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }
    const v = this.form.getRawValue();
    const data = {
      nom: v.nom.trim(),
      categorie: v.categorie.trim(),
      description: v.description.trim() || null,
      image: v.image.trim() || null,
    };
    const edition = this.edition();
    this.envoi.set(true);
    (edition
      ? this.gestion.modifierActivite(edition.id, data)
      : this.gestion.creerActivite(data)
    ).subscribe({
      next: () => {
        this.envoi.set(false);
        this.toasts.succes(this.langue.t(edition ? 'Activité modifiée.' : 'Activité ajoutée.'));
        this.fermer();
        this.charger();
      },
      error: (e) => {
        this.envoi.set(false);
        const champs = erreursChamps(e);
        this.erreurs.set(champs);
        if (Object.keys(champs).length === 0) this.toasts.erreur(messageErreur(e));
      },
    });
  }

  async supprimer(a: Activite): Promise<void> {
    const ok = await this.confirm.demander({
      titre: this.langue.t('Supprimer l’activité'),
      message: this.langue.t(
        '« {nom} » sera supprimée. Une activité utilisée par des séances ne peut pas être supprimée.',
        { nom: a.nom },
      ),
      confirmer: this.langue.t('Supprimer'),
      danger: true,
    });
    if (!ok) return;
    this.gestion.supprimerActivite(a.id).subscribe({
      next: () => {
        this.activites.update((liste) => liste?.filter((i) => i.id !== a.id) ?? null);
        this.toasts.succes(this.langue.t('Activité supprimée.'));
      },
      error: (e) => this.toasts.erreur(messageErreur(e)),
    });
  }

  private charger(): void {
    this.catalogue.activites().subscribe({
      next: (a) => this.activites.set(a),
      error: (e) =>
        this.chargementErreur.set(
          messageErreur(e, this.langue.t('Impossible de charger les activités.')),
        ),
    });
  }
}
