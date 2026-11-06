import { map } from 'rxjs';
import { Component, computed, inject, signal } from '@angular/core';
import {
  AbstractControl,
  FormBuilder,
  ReactiveFormsModule,
  ValidationErrors,
  Validators,
} from '@angular/forms';
import { erreursChamps, messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
import { Disponibilite, JOURS, Salle } from '../../../core/models';
import { CatalogueService } from '../../../core/services/catalogue.service';
import { DisponibiliteSaisie, GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { ConfirmService, EtatVideComponent, LoaderComponent } from '../../../shared/ui';
import { espaceCourant } from '../espace';

function creneauValide(group: AbstractControl): ValidationErrors | null {
  const v = group.value as {
    recurrent: boolean;
    jourSemaine: number | null;
    date: string;
    heureDebut: string;
    heureFin: string;
  };
  const erreurs: ValidationErrors = {};
  if (v.heureDebut && v.heureFin && v.heureFin <= v.heureDebut) erreurs['finAvantDebut'] = true;
  if (v.recurrent && !v.jourSemaine) erreurs['jour'] = true;
  if (!v.recurrent && !v.date) erreurs['date'] = true;
  return Object.keys(erreurs).length ? erreurs : null;
}

/** Disponibilités récurrentes ou ponctuelles d'un coach ou d'une salle (US-C03, US-G03). */
@Component({
  selector: 'app-disponibilites',
  imports: [ReactiveFormsModule, LoaderComponent, EtatVideComponent, ...I18N],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Disponibilités' | t }}</span>
        <h1>{{ (espace === 'coach' ? 'Mes disponibilités' : 'Disponibilités des salles') | t }}</h1>
        <p>
          {{
            'Déclarez vos créneaux récurrents (chaque semaine) ou ponctuels (une date précise).' | t
          }}
        </p>
      </div>
      @if (espace !== 'coach') {
        <label class="field" style="min-width:220px">
          <span>{{ 'Salle' | t }}</span>
          <select [value]="salleId() ?? ''" (change)="choisirSalle($any($event.target).value)">
            @for (s of salles(); track s.id) {
              <option [value]="s.id">{{ s.nom }}</option>
            }
          </select>
        </label>
      }
    </header>

    <div class="grid grid--2" style="align-items:start">
      <section class="panel" aria-labelledby="dispo-liste">
        <h2 id="dispo-liste">{{ 'Créneaux' | t }}</h2>
        @if (chargement()) {
          <app-loader />
        } @else if (creneaux().length === 0) {
          <app-etat-vide
            [titre]="'Aucun créneau' | t"
            [message]="'Ajoutez votre premier créneau avec le formulaire.' | t"
            icone="🕒"
          />
        } @else {
          @for (groupe of groupes(); track groupe.titre) {
            <h3 style="font-size:15px;margin:16px 0 8px">{{ groupe.titre | t }}</h3>
            <ul class="stack" style="list-style:none;margin:0;padding:0;gap:8px">
              @for (c of groupe.items; track c.id) {
                <li
                  class="row row--between"
                  style="padding:10px 12px;border:1px solid var(--border);border-radius:12px"
                >
                  <span>
                    @if (!c.recurrent) {
                      <strong>{{ c.date | dateL: 'EEE d MMM y' }}</strong> ·
                    }
                    {{ c.heureDebut }} – {{ c.heureFin }}
                  </span>
                  <span class="row" style="gap:6px">
                    <button type="button" class="btn btn--sm btn--ghost" (click)="editer(c)">
                      {{ 'Modifier' | t }}
                    </button>
                    <button type="button" class="btn btn--sm btn--danger" (click)="supprimer(c)">
                      {{ 'Supprimer' | t }}
                    </button>
                  </span>
                </li>
              }
            </ul>
          }
        }
      </section>

      <section class="panel" aria-labelledby="dispo-form">
        <h2 id="dispo-form">
          {{ (edition() ? 'Modifier le créneau' : 'Ajouter un créneau') | t }}
        </h2>
        <form class="form" [formGroup]="form" (ngSubmit)="enregistrer()" novalidate>
          <div class="tabs" role="radiogroup" [attr.aria-label]="'Type de créneau' | t">
            <button
              type="button"
              class="tab"
              role="radio"
              [attr.aria-checked]="form.controls.recurrent.value"
              [attr.aria-selected]="form.controls.recurrent.value"
              (click)="form.controls.recurrent.setValue(true)"
            >
              {{ 'Récurrent' | t }}
            </button>
            <button
              type="button"
              class="tab"
              role="radio"
              [attr.aria-checked]="!form.controls.recurrent.value"
              [attr.aria-selected]="!form.controls.recurrent.value"
              (click)="form.controls.recurrent.setValue(false)"
            >
              {{ 'Ponctuel' | t }}
            </button>
          </div>

          @if (form.controls.recurrent.value) {
            <div class="field">
              <label for="d-jour">{{ 'Jour de la semaine' | t }}</label>
              <select
                id="d-jour"
                formControlName="jourSemaine"
                [attr.aria-invalid]="soumis() && form.hasError('jour')"
              >
                <option [ngValue]="null">{{ 'Choisir…' | t }}</option>
                @for (j of jours; track j.n) {
                  <option [ngValue]="j.n">{{ j.nom | t }}</option>
                }
              </select>
              @if (soumis() && form.hasError('jour')) {
                <small class="field-error">{{ 'Choisissez le jour.' | t }}</small>
              }
              @if (serveur()['jourSemaine']) {
                <small class="field-error">{{ serveur()['jourSemaine'] }}</small>
              }
            </div>
          } @else {
            <div class="field">
              <label for="d-date">{{ 'Date' | t }}</label>
              <input
                id="d-date"
                type="date"
                formControlName="date"
                [attr.aria-invalid]="soumis() && form.hasError('date')"
              />
              @if (soumis() && form.hasError('date')) {
                <small class="field-error">{{ 'Choisissez la date.' | t }}</small>
              }
              @if (serveur()['date']) {
                <small class="field-error">{{ serveur()['date'] }}</small>
              }
            </div>
          }

          <div class="form-row">
            <div class="field">
              <label for="d-debut">{{ 'Début' | t }}</label>
              <input id="d-debut" type="time" formControlName="heureDebut" />
              @if (serveur()['heureDebut']) {
                <small class="field-error">{{ serveur()['heureDebut'] }}</small>
              }
            </div>
            <div class="field">
              <label for="d-fin">{{ 'Fin' | t }}</label>
              <input
                id="d-fin"
                type="time"
                formControlName="heureFin"
                [attr.aria-invalid]="soumis() && form.hasError('finAvantDebut')"
                aria-describedby="d-fin-err"
              />
              @if (soumis() && form.hasError('finAvantDebut')) {
                <small class="field-error" id="d-fin-err">{{
                  'La fin doit suivre le début.' | t
                }}</small>
              }
              @if (serveur()['heureFin']) {
                <small class="field-error">{{ serveur()['heureFin'] }}</small>
              }
            </div>
          </div>

          <div class="row">
            <button class="btn" type="submit" [disabled]="envoi()">
              {{ (edition() ? 'Enregistrer' : 'Ajouter') | t }}
            </button>
            @if (edition()) {
              <button class="btn btn--ghost" type="button" (click)="reinitialiser()">
                {{ 'Annuler' | t }}
              </button>
            }
          </div>
        </form>
      </section>
    </div>
  `,
})
export class DisponibilitesComponent {
  private readonly gestion = inject(GestionService);
  private readonly catalogue = inject(CatalogueService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly fb = inject(FormBuilder);
  private readonly langue = inject(LangueService);

  readonly espace = espaceCourant();
  readonly jours = JOURS.slice(1).map((nom, i) => ({ n: i + 1, nom }));
  readonly creneaux = signal<Disponibilite[]>([]);
  readonly salles = signal<Salle[]>([]);
  readonly salleId = signal<number | null>(null);
  readonly chargement = signal(true);
  readonly envoi = signal(false);
  readonly soumis = signal(false);
  readonly edition = signal<Disponibilite | null>(null);
  readonly serveur = signal<Record<string, string>>({});

  readonly form = this.fb.group(
    {
      recurrent: this.fb.nonNullable.control(true),
      jourSemaine: this.fb.control<number | null>(1),
      date: this.fb.nonNullable.control(''),
      heureDebut: this.fb.nonNullable.control('09:00', Validators.required),
      heureFin: this.fb.nonNullable.control('12:00', Validators.required),
    },
    { validators: creneauValide },
  );

  readonly groupes = computed(() => {
    const recurrents = this.creneaux().filter((c) => c.recurrent);
    const ponctuels = this.creneaux()
      .filter((c) => !c.recurrent)
      .sort((a, b) => (a.date ?? '').localeCompare(b.date ?? ''));
    const groupes = this.jours
      .map((j) => ({
        titre: j.nom,
        items: recurrents
          .filter((c) => c.jourSemaine === j.n)
          .sort((a, b) => a.heureDebut.localeCompare(b.heureDebut)),
      }))
      .filter((g) => g.items.length > 0);
    return ponctuels.length
      ? [...groupes, { titre: 'Créneaux ponctuels', items: ponctuels }]
      : groupes;
  });

  constructor() {
    if (this.espace === 'coach') {
      this.charger();
    } else {
      const salles =
        this.espace === 'gestionnaire' ? this.gestion.mesSalles() : this.catalogue.salles();
      salles.subscribe({
        next: (s) => {
          this.salles.set(s);
          this.salleId.set(s[0]?.id ?? null);
          this.charger();
        },
        error: () => this.chargement.set(false),
      });
    }
  }

  choisirSalle(valeur: string): void {
    this.salleId.set(Number(valeur) || null);
    this.reinitialiser();
    this.charger();
  }

  charger(): void {
    this.chargement.set(true);
    const salleId = this.salleId();
    if (this.espace !== 'coach' && !salleId) {
      // Aucune salle : rien à afficher.
      this.creneaux.set([]);
      this.chargement.set(false);
      return;
    }
    // Coach : son profil (créé s'il n'existe pas encore) porte ses disponibilités.
    const requete =
      this.espace === 'coach'
        ? this.gestion.profilCoach().pipe(map((p) => p.disponibilites))
        : this.catalogue.disponibilites({ salleId: salleId ?? undefined });
    requete.subscribe({
      next: (d) => {
        this.creneaux.set(d);
        this.chargement.set(false);
      },
      error: (error) => {
        this.toasts.erreur(messageErreur(error));
        this.chargement.set(false);
      },
    });
  }

  editer(c: Disponibilite): void {
    this.edition.set(c);
    this.soumis.set(false);
    this.serveur.set({});
    this.form.setValue({
      recurrent: c.recurrent,
      jourSemaine: c.jourSemaine,
      date: c.date ?? '',
      heureDebut: c.heureDebut,
      heureFin: c.heureFin,
    });
  }

  reinitialiser(): void {
    this.edition.set(null);
    this.soumis.set(false);
    this.serveur.set({});
    this.form.reset({
      recurrent: true,
      jourSemaine: 1,
      date: '',
      heureDebut: '09:00',
      heureFin: '12:00',
    });
  }

  enregistrer(): void {
    this.soumis.set(true);
    this.serveur.set({});
    if (this.form.invalid) return;
    if (this.espace !== 'coach' && !this.salleId()) {
      this.toasts.erreur(this.langue.t('Créez d’abord une salle.'));
      return;
    }
    const v = this.form.getRawValue();
    const data: DisponibiliteSaisie = {
      recurrent: v.recurrent,
      jourSemaine: v.recurrent ? v.jourSemaine : null,
      date: v.recurrent ? null : v.date,
      heureDebut: v.heureDebut,
      heureFin: v.heureFin,
      salleId: this.espace === 'coach' ? null : this.salleId(),
    };
    const edition = this.edition();
    this.envoi.set(true);
    const requete = edition
      ? this.gestion.modifierDisponibilite(edition.id, data)
      : this.gestion.creerDisponibilite(data);
    requete.subscribe({
      next: () => {
        this.envoi.set(false);
        this.toasts.succes(this.langue.t(edition ? 'Créneau modifié.' : 'Créneau ajouté.'));
        this.reinitialiser();
        this.charger();
      },
      error: (error: unknown) => {
        this.envoi.set(false);
        this.serveur.set(erreursChamps(error));
        if (Object.keys(erreursChamps(error)).length === 0)
          this.toasts.erreur(messageErreur(error));
      },
    });
  }

  async supprimer(c: Disponibilite): Promise<void> {
    const ok = await this.confirm.demander({
      titre: this.langue.t('Supprimer ce créneau ?'),
      message: `${c.heureDebut} – ${c.heureFin}`,
      confirmer: this.langue.t('Supprimer'),
      danger: true,
    });
    if (!ok) return;
    this.gestion.supprimerDisponibilite(c.id).subscribe({
      next: () => {
        this.toasts.succes(this.langue.t('Créneau supprimé.'));
        this.charger();
      },
      error: (error) => this.toasts.erreur(messageErreur(error)),
    });
  }
}
