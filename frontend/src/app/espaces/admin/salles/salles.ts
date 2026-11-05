import { Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Salle } from '../../../core/models';
import { CatalogueService } from '../../../core/services/catalogue.service';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import { ConfirmService, EtatVideComponent, EtoilesComponent, LoaderComponent } from '../../../shared/ui';

/** Toutes les salles de la plateforme (US-A03) : modification et suppression. */
@Component({
  selector: 'app-salles-admin',
  imports: [RouterLink, LoaderComponent, EtatVideComponent, EtoilesComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Administration</span>
        <h1>Salles</h1>
        <p>Toutes les salles de la plateforme, quel que soit leur gestionnaire.</p>
      </div>
      <a class="btn" routerLink="/admin/salles/nouvelle">+ Nouvelle salle</a>
    </header>

    <label class="field search">
      <span>Rechercher</span>
      <input type="search" class="input" placeholder="Nom ou ville" [value]="q()" (input)="q.set($any($event.target).value)">
    </label>

    @if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (!salles()) {
      <app-loader />
    } @else if (filtrees().length === 0) {
      <app-etat-vide titre="Aucune salle" message="Aucune salle ne correspond à cette recherche." />
    } @else {
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr><th scope="col">Nom</th><th scope="col">Ville</th><th scope="col">Note</th><th scope="col"><span class="sr-only">Actions</span></th></tr>
          </thead>
          <tbody>
            @for (s of filtrees(); track s.id) {
              <tr>
                <td><a [routerLink]="['/salles', s.id]"><strong>{{ s.nom }}</strong></a></td>
                <td>{{ s.codePostal }} {{ s.ville }}</td>
                <td><app-etoiles [note]="s.noteMoyenne" [nbAvis]="s.nbAvis ?? null" /></td>
                <td>
                  <div class="actions">
                    <a class="btn btn--sm btn--ghost" [routerLink]="['/admin/salles', s.id]">Modifier</a>
                    <button type="button" class="btn btn--sm btn--danger" [disabled]="enCours() === s.id" (click)="supprimer(s)">Supprimer</button>
                  </div>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    }
  `,
  styles: `.search { max-width: 380px; margin-bottom: 18px; }`,
})
export class SallesAdminComponent {
  private readonly catalogue = inject(CatalogueService);
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);

  readonly salles = signal<Salle[] | null>(null);
  readonly q = signal('');
  readonly erreur = signal('');
  readonly enCours = signal<number | null>(null);

  readonly filtrees = computed(() => {
    const q = this.q().trim().toLowerCase();
    const salles = this.salles() ?? [];
    return q ? salles.filter(s => `${s.nom} ${s.ville} ${s.codePostal}`.toLowerCase().includes(q)) : salles;
  });

  constructor() {
    this.catalogue.salles().subscribe({
      next: s => this.salles.set(s),
      error: e => this.erreur.set(messageErreur(e, 'Impossible de charger les salles.')),
    });
  }

  async supprimer(s: Salle): Promise<void> {
    const ok = await this.confirm.demander({
      titre: 'Supprimer la salle',
      message: `« ${s.nom} » sera supprimée définitivement. Une salle qui a des séances ne peut pas être supprimée.`,
      confirmer: 'Supprimer',
      danger: true,
    });
    if (!ok) return;
    this.enCours.set(s.id);
    this.gestion.supprimerSalle(s.id).subscribe({
      next: () => {
        this.enCours.set(null);
        this.salles.update(liste => liste?.filter(i => i.id !== s.id) ?? null);
        this.toasts.succes('Salle supprimée.');
      },
      error: e => {
        this.enCours.set(null);
        this.toasts.erreur(messageErreur(e));
      },
    });
  }
}
