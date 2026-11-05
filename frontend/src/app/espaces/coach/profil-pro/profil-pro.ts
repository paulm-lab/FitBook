import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { erreursChamps, messageErreur } from '../../../core/http';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { LoaderComponent } from '../../../shared/ui';

const MAX_SPECIALITES = 10;

/** Profil professionnel du coach (US-C01), affiché sur sa fiche publique /coachs/:id. */
@Component({
  selector: 'app-profil-pro',
  imports: [ReactiveFormsModule, RouterLink, LoaderComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Profil professionnel</span>
        <h1>Ma fiche coach</h1>
        <p>Ces informations sont visibles par tous les visiteurs.</p>
      </div>
      @if (coachId()) {
        <a class="btn btn--ghost" [routerLink]="['/coachs', coachId()]">Voir ma fiche publique →</a>
      }
    </header>

    @if (chargement()) {
      <app-loader />
    } @else {
      @if (erreur()) {
        <p class="alert alert--error" role="alert">{{ erreur() }}</p>
      }
      <form class="panel form" [formGroup]="form" (ngSubmit)="enregistrer()" novalidate>
        <div class="field">
          <label for="p-bio">Biographie</label>
          <textarea
            id="p-bio"
            formControlName="biographie"
            maxlength="3000"
            rows="6"
            aria-describedby="p-bio-aide"
            [attr.aria-invalid]="!!serveur()['biographie']"
          ></textarea>
          <span class="field-hint" id="p-bio-aide"
            >{{ form.controls.biographie.value.length }} / 3000 caractères</span
          >
          @if (serveur()['biographie']) {
            <small class="field-error">{{ serveur()['biographie'] }}</small>
          }
        </div>

        <div class="field">
          <label for="p-spec"
            >Spécialités
            <span class="field-hint"
              >(Entrée pour ajouter, {{ maxSpecialites }} au maximum)</span
            ></label
          >
          <div class="chips" style="margin-bottom:6px">
            @for (s of specialites(); track s) {
              <span class="chip"
                >{{ s }}
                <button type="button" (click)="retirer(s)" [attr.aria-label]="'Retirer ' + s">
                  ×
                </button></span
              >
            }
          </div>
          <input
            id="p-spec"
            #spec
            maxlength="50"
            [disabled]="specialites().length >= maxSpecialites"
            placeholder="Ex. Yoga, Pilates…"
            (keydown.enter)="$event.preventDefault(); ajouter(spec)"
            (blur)="ajouter(spec)"
          />
          @if (serveur()['specialites']) {
            <small class="field-error">{{ serveur()['specialites'] }}</small>
          }
        </div>

        <div class="form-row form-row--3">
          <div class="field">
            <label for="p-exp">Années d'expérience</label>
            <input
              id="p-exp"
              type="number"
              min="0"
              max="60"
              formControlName="experienceAnnees"
              [attr.aria-invalid]="invalide('experienceAnnees')"
            />
            @if (invalide('experienceAnnees')) {
              <small class="field-error">{{
                serveur()['experienceAnnees'] || 'Entre 0 et 60 ans.'
              }}</small>
            }
          </div>
          <div class="field">
            <label for="p-tarif">Tarif horaire (€)</label>
            <input
              id="p-tarif"
              type="number"
              min="0"
              max="1000"
              step="0.5"
              formControlName="tarifHoraire"
              [attr.aria-invalid]="invalide('tarifHoraire')"
            />
            @if (invalide('tarifHoraire')) {
              <small class="field-error">{{
                serveur()['tarifHoraire'] || 'Entre 0 et 1000 €.'
              }}</small>
            }
          </div>
          <div class="field">
            <label for="p-ville">Ville</label>
            <input
              id="p-ville"
              formControlName="ville"
              maxlength="100"
              autocomplete="address-level2"
            />
            @if (serveur()['ville']) {
              <small class="field-error">{{ serveur()['ville'] }}</small>
            }
          </div>
        </div>

        <div class="field">
          <label for="p-certif">Certifications</label>
          <textarea
            id="p-certif"
            formControlName="certifications"
            maxlength="2000"
            rows="3"
          ></textarea>
          @if (serveur()['certifications']) {
            <small class="field-error">{{ serveur()['certifications'] }}</small>
          }
        </div>

        <div class="row">
          <button class="btn" type="submit" [disabled]="envoi()" [attr.aria-busy]="envoi()">
            {{ envoi() ? 'Enregistrement…' : 'Enregistrer mon profil' }}
          </button>
        </div>
      </form>
    }
  `,
})
export class ProfilProComponent {
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly fb = inject(FormBuilder);

  readonly maxSpecialites = MAX_SPECIALITES;
  readonly chargement = signal(true);
  readonly envoi = signal(false);
  readonly erreur = signal('');
  readonly serveur = signal<Record<string, string>>({});
  readonly specialites = signal<string[]>([]);
  readonly coachId = signal<number | null>(null);

  readonly form = this.fb.group({
    biographie: this.fb.nonNullable.control('', Validators.maxLength(3000)),
    experienceAnnees: this.fb.control<number | null>(null, [Validators.min(0), Validators.max(60)]),
    tarifHoraire: this.fb.control<number | null>(null, [Validators.min(0), Validators.max(1000)]),
    ville: this.fb.nonNullable.control(''),
    certifications: this.fb.nonNullable.control(''),
  });

  constructor() {
    this.gestion.profilCoach().subscribe({
      next: (p) => {
        this.coachId.set(p.id);
        this.specialites.set(p.specialites ?? []);
        this.form.patchValue({
          biographie: p.biographie ?? '',
          experienceAnnees: p.experienceAnnees,
          tarifHoraire: p.tarifHoraire !== null ? Number(p.tarifHoraire) : null,
          ville: p.ville ?? '',
          certifications: p.certification ?? '',
        });
        this.chargement.set(false);
      },
      error: (error) => {
        this.erreur.set(messageErreur(error, 'Impossible de charger votre profil.'));
        this.chargement.set(false);
      },
    });
  }

  invalide(champ: 'experienceAnnees' | 'tarifHoraire'): boolean {
    return !!this.serveur()[champ] || this.form.controls[champ].invalid;
  }

  ajouter(champ: HTMLInputElement): void {
    const valeur = champ.value.trim();
    champ.value = '';
    if (!valeur || this.specialites().length >= MAX_SPECIALITES) return;
    if (this.specialites().some((s) => s.toLowerCase() === valeur.toLowerCase())) return;
    this.specialites.update((liste) => [...liste, valeur.slice(0, 50)]);
  }

  retirer(specialite: string): void {
    this.specialites.update((liste) => liste.filter((s) => s !== specialite));
  }

  enregistrer(): void {
    this.serveur.set({});
    this.erreur.set('');
    if (this.form.invalid) return;
    const v = this.form.getRawValue();
    this.envoi.set(true);
    this.gestion
      .modifierProfilCoach({
        biographie: v.biographie.trim() || null,
        specialites: this.specialites(),
        experienceAnnees: v.experienceAnnees,
        tarifHoraire: v.tarifHoraire,
        ville: v.ville.trim() || null,
        certifications: v.certifications.trim() || null,
      })
      .subscribe({
        next: () => {
          this.envoi.set(false);
          this.toasts.succes('Profil enregistré.');
        },
        error: (error: unknown) => {
          this.envoi.set(false);
          const champs = erreursChamps(error);
          this.serveur.set(champs);
          this.erreur.set(
            Object.keys(champs).length ? 'Certains champs sont invalides.' : messageErreur(error),
          );
        },
      });
  }
}
