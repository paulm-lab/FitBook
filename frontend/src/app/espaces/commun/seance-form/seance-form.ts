import { Component, effect, inject, input, signal } from '@angular/core';
import { AbstractControl, FormBuilder, ReactiveFormsModule, ValidationErrors, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { erreursChamps, messageErreur } from '../../../core/http';
import { Activite, Coach, NIVEAUX, Niveau, Salle } from '../../../core/models';
import { CatalogueService } from '../../../core/services/catalogue.service';
import { GestionService, SeanceSaisie } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { LoaderComponent } from '../../../shared/ui';
import { dateLocale, espaceCourant, heureLocale } from '../espace';

/** Fin après début (RG-09) et séance dans le futur, vérifiés sur le groupe entier. */
function horairesValides(group: AbstractControl): ValidationErrors | null {
  const { date, heureDebut, heureFin } = group.value as { date: string; heureDebut: string; heureFin: string };
  if (!date || !heureDebut || !heureFin) return null;
  const debut = new Date(`${date}T${heureDebut}`);
  const fin = new Date(`${date}T${heureFin}`);
  const erreurs: ValidationErrors = {};
  if (fin <= debut) erreurs['finAvantDebut'] = true;
  if (debut <= new Date()) erreurs['passe'] = true;
  return Object.keys(erreurs).length ? erreurs : null;
}

/** Création et modification d'une séance (US-C02, US-G02). */
@Component({
  selector: 'app-seance-form',
  imports: [ReactiveFormsModule, RouterLink, LoaderComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ id() ? 'Modification' : 'Nouvelle séance' }}</span>
        <h1>{{ id() ? 'Modifier la séance' : 'Créer une séance' }}</h1>
        <p>Les horaires sont saisis à l'heure locale.</p>
      </div>
      <a class="btn btn--ghost" [routerLink]="base + '/seances'">← Retour à la liste</a>
    </header>

    @if (chargement()) {
      <app-loader />
    } @else {
      @if (annulee()) {
        <p class="alert alert--error" role="alert">Cette séance est annulée : elle ne peut plus être modifiée.</p>
      }
      @if (erreur()) { <p class="alert alert--error" role="alert">{{ erreur() }}</p> }

      <form class="panel form" [formGroup]="form" (ngSubmit)="enregistrer()" novalidate>
        <fieldset class="form" [disabled]="annulee()" style="border:0;margin:0;padding:0">
          <div class="field">
            <label for="sf-titre">Titre</label>
            <input id="sf-titre" formControlName="titre" maxlength="100" [attr.aria-invalid]="invalide('titre')" aria-describedby="sf-titre-err">
            @if (message('titre'); as m) { <small class="field-error" id="sf-titre-err">{{ m }}</small> }
          </div>

          <div class="field">
            <label for="sf-desc">Description</label>
            <textarea id="sf-desc" formControlName="description" maxlength="2000"></textarea>
          </div>

          <div class="form-row--3 form-row">
            <div class="field">
              <label for="sf-activite">Activité</label>
              <select id="sf-activite" formControlName="activiteId" [attr.aria-invalid]="invalide('activiteId')" aria-describedby="sf-activite-err">
                <option [ngValue]="null">Choisir…</option>
                @for (a of activites(); track a.id) { <option [ngValue]="a.id">{{ a.nom }}</option> }
              </select>
              @if (message('activiteId'); as m) { <small class="field-error" id="sf-activite-err">{{ m }}</small> }
            </div>

            <div class="field">
              <label for="sf-salle">Salle{{ espace === 'gestionnaire' ? '' : ' (facultative)' }}</label>
              <select id="sf-salle" formControlName="salleId" [attr.aria-invalid]="invalide('salleId')" aria-describedby="sf-salle-err">
                <option [ngValue]="null">{{ espace === 'gestionnaire' ? 'Choisir…' : 'Aucune (en extérieur / à domicile)' }}</option>
                @for (s of salles(); track s.id) { <option [ngValue]="s.id">{{ s.nom }} — {{ s.ville }}</option> }
              </select>
              @if (message('salleId'); as m) { <small class="field-error" id="sf-salle-err">{{ m }}</small> }
            </div>

            @if (espace !== 'coach') {
              <div class="field">
                <label for="sf-coach">Coach (facultatif)</label>
                <select id="sf-coach" formControlName="coachId" [attr.aria-invalid]="invalide('coachId')" aria-describedby="sf-coach-err">
                  <option [ngValue]="null">Aucun</option>
                  @for (c of coachs(); track c.id) { <option [ngValue]="c.id">{{ c.prenom }} {{ c.nom }}</option> }
                </select>
                @if (message('coachId'); as m) { <small class="field-error" id="sf-coach-err">{{ m }}</small> }
              </div>
            }
          </div>

          <div class="form-row form-row--3">
            <div class="field">
              <label for="sf-date">Date</label>
              <input id="sf-date" type="date" formControlName="date" [min]="aujourdhui" [attr.aria-invalid]="invalide('date') || form.hasError('passe')" aria-describedby="sf-date-err">
              @if (message('date'); as m) { <small class="field-error" id="sf-date-err">{{ m }}</small> }
            </div>
            <div class="field">
              <label for="sf-debut">Heure de début</label>
              <input id="sf-debut" type="time" formControlName="heureDebut" [attr.aria-invalid]="invalide('heureDebut')">
            </div>
            <div class="field">
              <label for="sf-fin">Heure de fin</label>
              <input id="sf-fin" type="time" formControlName="heureFin" [attr.aria-invalid]="invalide('heureFin') || form.hasError('finAvantDebut')" aria-describedby="sf-fin-err">
              @if (message('heureFin'); as m) { <small class="field-error" id="sf-fin-err">{{ m }}</small> }
            </div>
          </div>

          <div class="form-row form-row--3">
            <div class="field">
              <label for="sf-capacite">Capacité</label>
              <input id="sf-capacite" type="number" min="1" max="500" formControlName="capacite" [attr.aria-invalid]="invalide('capacite')" aria-describedby="sf-capacite-err">
              @if (message('capacite'); as m) { <small class="field-error" id="sf-capacite-err">{{ m }}</small> }
            </div>
            <div class="field">
              <label for="sf-prix">Prix (€)</label>
              <input id="sf-prix" type="number" min="0" step="0.5" formControlName="prix" [attr.aria-invalid]="invalide('prix')" aria-describedby="sf-prix-err">
              @if (message('prix'); as m) { <small class="field-error" id="sf-prix-err">{{ m }}</small> }
            </div>
            <div class="field">
              <label for="sf-niveau">Niveau</label>
              <select id="sf-niveau" formControlName="niveau">
                @for (n of niveaux; track n[0]) { <option [value]="n[0]">{{ n[1] }}</option> }
              </select>
              @if (message('niveau'); as m) { <small class="field-error">{{ m }}</small> }
            </div>
          </div>
        </fieldset>

        @if (!annulee()) {
          <div class="row">
            <button type="submit" class="btn" [disabled]="envoi()" [attr.aria-busy]="envoi()">{{ envoi() ? 'Enregistrement…' : id() ? 'Enregistrer les modifications' : 'Créer la séance' }}</button>
            <a class="btn btn--ghost" [routerLink]="base + '/seances'">Annuler</a>
          </div>
        }
      </form>
    }
  `,
})
export class SeanceFormComponent {
  /** Identifiant de la séance (route seances/:id) ; absent en création. */
  readonly id = input<string>();

  private readonly fb = inject(FormBuilder);
  private readonly catalogue = inject(CatalogueService);
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly espace = espaceCourant();
  readonly base = `/${this.espace}`;
  readonly aujourdhui = dateLocale(new Date());
  readonly niveaux = Object.entries(NIVEAUX) as [Niveau, string][];

  readonly activites = signal<Activite[]>([]);
  readonly salles = signal<Salle[]>([]);
  readonly coachs = signal<Coach[]>([]);
  readonly chargement = signal(false);
  readonly envoi = signal(false);
  readonly annulee = signal(false);
  readonly erreur = signal('');
  readonly serveur = signal<Record<string, string>>({});
  private readonly soumis = signal(false);

  readonly form = this.fb.group(
    {
      titre: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(100)]),
      description: this.fb.nonNullable.control(''),
      activiteId: this.fb.control<number | null>(null, Validators.required),
      salleId: this.fb.control<number | null>(null, this.espace === 'gestionnaire' ? Validators.required : []),
      coachId: this.fb.control<number | null>(null),
      date: this.fb.nonNullable.control('', Validators.required),
      heureDebut: this.fb.nonNullable.control('18:00', Validators.required),
      heureFin: this.fb.nonNullable.control('19:00', Validators.required),
      capacite: this.fb.control<number | null>(12, [Validators.required, Validators.min(1), Validators.max(500)]),
      prix: this.fb.control<number | null>(0, [Validators.required, Validators.min(0), Validators.max(1000)]),
      niveau: this.fb.nonNullable.control<Niveau>('debutant'),
    },
    { validators: horairesValides },
  );

  private readonly correspondance: Record<string, string> = { debut: 'date', fin: 'heureFin' };


  constructor() {
    this.catalogue.activites().subscribe({ next: a => this.activites.set(a) });
    const salles = this.espace === 'gestionnaire' ? this.gestion.mesSalles() : this.catalogue.salles();
    salles.subscribe({ next: s => this.salles.set(s) });
    if (this.espace !== 'coach') {
      this.catalogue.coachs().subscribe({ next: c => this.coachs.set(c) });
    }

    // Date proposée depuis le calendrier (?date=AAAA-MM-JJ).
    const date = this.route.snapshot.queryParamMap.get('date');
    if (date && /^\d{4}-\d{2}-\d{2}$/.test(date)) this.form.controls.date.setValue(date);

    effect(() => {
      const id = Number(this.id());
      if (id > 0) this.chargerSeance(id);
    });
  }

  invalide(champ: string): boolean {
    const control = this.form.get(champ);
    return !!this.serveur()[champ] || (!!control && control.invalid && (control.touched || this.soumis()));
  }

  message(champ: string): string | null {
    if (this.serveur()[champ]) return this.serveur()[champ];
    const control = this.form.get(champ);
    const visible = control && (control.touched || this.soumis());
    if (champ === 'heureFin' && this.form.hasError('finAvantDebut') && (visible || this.soumis())) return 'La fin doit être postérieure au début.';
    if (champ === 'date' && this.form.hasError('passe') && (visible || this.soumis())) return 'La séance doit commencer dans le futur.';
    if (!control || !visible || control.valid) return null;
    if (control.hasError('required')) return 'Ce champ est obligatoire.';
    if (champ === 'capacite') return 'La capacité doit être comprise entre 1 et 500.';
    if (champ === 'prix') return 'Le prix doit être compris entre 0 et 1000 €.';
    if (control.hasError('maxlength')) return 'Texte trop long.';
    return 'Valeur invalide.';
  }

  enregistrer(): void {
    this.soumis.set(true);
    this.serveur.set({});
    this.erreur.set('');
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const v = this.form.getRawValue();
    const data: SeanceSaisie = {
      titre: v.titre.trim(),
      description: v.description.trim() || null,
      activiteId: v.activiteId,
      salleId: v.salleId,
      coachId: this.espace === 'coach' ? null : v.coachId,
      debut: new Date(`${v.date}T${v.heureDebut}`).toISOString(),
      fin: new Date(`${v.date}T${v.heureFin}`).toISOString(),
      capacite: v.capacite,
      prix: v.prix,
      niveau: v.niveau,
    };

    this.envoi.set(true);
    const id = Number(this.id());
    const requete = id > 0 ? this.gestion.modifierSeance(id, data) : this.gestion.creerSeance(data);
    requete.subscribe({
      next: () => {
        this.toasts.succes(id > 0 ? 'Séance modifiée.' : 'Séance créée.');
        void this.router.navigateByUrl(`${this.base}/seances`);
      },
      error: (error: unknown) => {
        this.envoi.set(false);
        const champs = erreursChamps(error);
        const mappes: Record<string, string> = {};
        for (const [cle, msg] of Object.entries(champs)) mappes[this.correspondance[cle] ?? cle] = msg;
        this.serveur.set(mappes);
        this.erreur.set(Object.keys(mappes).length ? 'Certains champs sont invalides.' : messageErreur(error));
      },
    });
  }

  private chargerSeance(id: number): void {
    this.chargement.set(true);
    this.catalogue.seance(id).subscribe({
      next: s => {
        const debut = new Date(s.dateDebut);
        const fin = new Date(s.dateFin);
        this.form.patchValue({
          titre: s.titre,
          description: s.description ?? '',
          activiteId: s.activite?.id ?? null,
          salleId: s.salle?.id ?? null,
          coachId: s.coach?.id ?? null,
          date: dateLocale(debut),
          heureDebut: heureLocale(debut),
          heureFin: heureLocale(fin),
          capacite: s.capaciteMax,
          prix: Number(s.prix),
          niveau: s.niveau,
        });
        this.annulee.set(s.statut === 'annulee');
        // Une séance passée garde sa date : on ne bloque pas la saisie sur ce seul motif.
        if (debut <= new Date()) this.form.setValidators(null);
        this.form.updateValueAndValidity();
        this.chargement.set(false);
      },
      error: error => {
        this.erreur.set(messageErreur(error, 'Séance introuvable.'));
        this.chargement.set(false);
      },
    });
  }
}
