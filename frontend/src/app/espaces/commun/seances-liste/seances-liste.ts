import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { Page, Salle, SeanceResume } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import {
  ConfirmService,
  EtatVideComponent,
  LoaderComponent,
  PaginationComponent,
} from '../../../shared/ui';
import { espaceCourant } from '../espace';

/** Liste des séances d'un espace (coach, gestionnaire, admin) : à venir / passées, actions. */
@Component({
  selector: 'app-seances-liste',
  imports: [
    RouterLink,
    DatePipe,
    DecimalPipe,
    LoaderComponent,
    EtatVideComponent,
    PaginationComponent,
  ],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Planning</span>
        <h1>
          {{
            espace === 'gestionnaire'
              ? 'Cours et séances'
              : espace === 'admin'
                ? 'Toutes les séances'
                : 'Mes séances'
          }}
        </h1>
        <p>Créez, modifiez ou annulez vos séances et consultez les inscrits.</p>
      </div>
      <a class="btn" [routerLink]="base + '/seances/nouvelle'">+ Nouvelle séance</a>
    </header>

    <div class="row row--between">
      <div class="tabs" role="tablist" aria-label="Période">
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="periode() === 'a_venir'"
          (click)="changerPeriode('a_venir')"
        >
          À venir
        </button>
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="periode() === 'passees'"
          (click)="changerPeriode('passees')"
        >
          Passées
        </button>
      </div>
      @if (espace === 'gestionnaire') {
        <label class="field" style="min-width: 220px">
          <span>Salle</span>
          <select [value]="salleId() ?? ''" (change)="changerSalle($any($event.target).value)">
            <option value="">Toutes mes salles</option>
            @for (s of salles(); track s.id) {
              <option [value]="s.id">{{ s.nom }}</option>
            }
          </select>
        </label>
      }
    </div>

    @if (chargement()) {
      <app-loader />
    } @else if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (page(); as p) {
      @if (p.items.length === 0) {
        <app-etat-vide
          titre="Aucune séance"
          [message]="
            periode() === 'a_venir'
              ? 'Aucune séance programmée pour le moment.'
              : 'Aucune séance passée.'
          "
          icone="📅"
        >
          <a class="btn btn--sm" [routerLink]="base + '/seances/nouvelle'">Créer une séance</a>
        </app-etat-vide>
      } @else {
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">Date</th>
                <th scope="col">Séance</th>
                <th scope="col">Lieu / coach</th>
                <th scope="col">Inscrits</th>
                <th scope="col">Prix</th>
                <th scope="col">Statut</th>
                <th scope="col"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              @for (s of p.items; track s.id) {
                @let inscrits = s.capaciteMax - s.placesDisponibles;
                <tr>
                  <td>
                    {{ s.dateDebut | date: 'EEE d MMM y' }}<br /><span class="muted"
                      >{{ s.dateDebut | date: 'HH:mm' }} – {{ s.dateFin | date: 'HH:mm' }}</span
                    >
                  </td>
                  <td>
                    <strong>{{ s.titre }}</strong
                    ><br /><span class="muted">{{ s.activite?.nom }}</span>
                  </td>
                  <td>
                    {{ s.salle?.nom ?? '—' }}
                    @if (s.coach) {
                      <br /><span class="muted">{{ s.coach.prenom }} {{ s.coach.nom }}</span>
                    }
                  </td>
                  <td>{{ inscrits }} / {{ s.capaciteMax }}</td>
                  <td>{{ +s.prix === 0 ? 'Gratuit' : (s.prix | number: '1.2-2') + ' €' }}</td>
                  <td>
                    <span class="badge badge--{{ s.statut }}">{{
                      s.statut === 'active' ? 'Active' : 'Annulée'
                    }}</span>
                  </td>
                  <td>
                    <div class="actions">
                      <a
                        class="btn btn--sm btn--ghost"
                        [routerLink]="base + '/seances/' + s.id + '/inscrits'"
                        >Inscrits</a
                      >
                      @if (s.statut === 'active') {
                        <a class="btn btn--sm btn--ghost" [routerLink]="base + '/seances/' + s.id"
                          >Modifier</a
                        >
                        @if (periode() === 'a_venir') {
                          <button type="button" class="btn btn--sm btn--ghost" (click)="annuler(s)">
                            Annuler
                          </button>
                        }
                      }
                      @if (inscrits === 0) {
                        <button
                          type="button"
                          class="btn btn--sm btn--danger"
                          (click)="supprimer(s)"
                        >
                          Supprimer
                        </button>
                      }
                    </div>
                  </td>
                </tr>
              }
            </tbody>
          </table>
        </div>
        <app-pagination [page]="p.page" [pages]="p.pages" (changer)="charger($event)" />
      }
    }
  `,
})
export class SeancesListeComponent {
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly route = inject(ActivatedRoute);

  readonly espace = espaceCourant();
  readonly base = `/${this.espace}`;
  readonly periode = signal<'a_venir' | 'passees'>('a_venir');
  readonly salleId = signal<number | null>(null);
  readonly salles = signal<Salle[]>([]);
  readonly page = signal<Page<SeanceResume> | null>(null);
  readonly chargement = signal(true);
  readonly erreur = signal('');

  constructor() {
    const salle = Number(this.route.snapshot.queryParamMap.get('salleId'));
    if (salle > 0) this.salleId.set(salle);
    if (this.espace === 'gestionnaire') {
      this.gestion
        .mesSalles()
        .subscribe({ next: (s) => this.salles.set(s), error: () => this.salles.set([]) });
    }
    this.charger(1);
  }

  changerPeriode(periode: 'a_venir' | 'passees'): void {
    this.periode.set(periode);
    this.charger(1);
  }

  changerSalle(valeur: string): void {
    this.salleId.set(valeur ? Number(valeur) : null);
    this.charger(1);
  }

  charger(page: number): void {
    this.chargement.set(true);
    this.erreur.set('');
    this.gestion
      .seances(this.espace, { periode: this.periode(), page, salleId: this.salleId() ?? undefined })
      .subscribe({
        next: (reponse) => {
          this.page.set(
            Array.isArray(reponse)
              ? {
                  items: reponse,
                  page: 1,
                  parPage: reponse.length,
                  total: reponse.length,
                  pages: 1,
                }
              : reponse,
          );
          this.chargement.set(false);
        },
        error: (error) => {
          this.erreur.set(messageErreur(error, 'Impossible de charger les séances.'));
          this.chargement.set(false);
        },
      });
  }

  async annuler(seance: SeanceResume): Promise<void> {
    const inscrits = seance.capaciteMax - seance.placesDisponibles;
    const ok = await this.confirm.demander({
      titre: `Annuler « ${seance.titre} » ?`,
      message:
        inscrits > 0
          ? `Les ${inscrits} sportif(s) inscrit(s) seront prévenus par e-mail et leurs réservations annulées. Cette action est définitive.`
          : 'La séance ne sera plus réservable. Cette action est définitive.',
      confirmer: 'Annuler la séance',
      danger: true,
    });
    if (!ok) return;
    this.gestion.annulerSeance(seance.id).subscribe({
      next: (r) => {
        this.toasts.succes(`Séance annulée. ${r.sportifsPrevenus} sportif(s) prévenu(s).`);
        this.charger(this.page()?.page ?? 1);
      },
      error: (error) => this.toasts.erreur(messageErreur(error)),
    });
  }

  async supprimer(seance: SeanceResume): Promise<void> {
    const ok = await this.confirm.demander({
      titre: `Supprimer « ${seance.titre} » ?`,
      message: 'La séance sera définitivement supprimée.',
      confirmer: 'Supprimer',
      danger: true,
    });
    if (!ok) return;
    this.gestion.supprimerSeance(seance.id).subscribe({
      next: () => {
        this.toasts.succes('Séance supprimée.');
        this.charger(this.page()?.page ?? 1);
      },
      error: (error) => this.toasts.erreur(messageErreur(error)),
    });
  }
}
