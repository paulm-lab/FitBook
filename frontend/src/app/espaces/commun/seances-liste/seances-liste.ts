import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { messageErreur } from '../../../core/http';
import { LangueService } from '../../../core/i18n/langue.service';
import { I18N } from '../../../core/i18n/pipes';
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
  imports: [RouterLink, LoaderComponent, EtatVideComponent, PaginationComponent, ...I18N],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">{{ 'Planning' | t }}</span>
        <h1>
          {{
            (espace === 'gestionnaire'
              ? 'Cours et séances'
              : espace === 'admin'
                ? 'Toutes les séances'
                : 'Mes séances'
            ) | t
          }}
        </h1>
        <p>{{ 'Créez, modifiez ou annulez vos séances et consultez les inscrits.' | t }}</p>
      </div>
      <a class="btn" [routerLink]="base + '/seances/nouvelle'">{{ '+ Nouvelle séance' | t }}</a>
    </header>

    <div class="row row--between">
      <div class="tabs" role="tablist" [attr.aria-label]="'Période' | t">
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="periode() === 'a_venir'"
          (click)="changerPeriode('a_venir')"
        >
          {{ 'À venir' | t }}
        </button>
        <button
          type="button"
          class="tab"
          role="tab"
          [attr.aria-selected]="periode() === 'passees'"
          (click)="changerPeriode('passees')"
        >
          {{ 'Passées' | t }}
        </button>
      </div>
      @if (espace === 'gestionnaire') {
        <label class="field" style="min-width: 220px">
          <span>{{ 'Salle' | t }}</span>
          <select [value]="salleId() ?? ''" (change)="changerSalle($any($event.target).value)">
            <option value="">{{ 'Toutes mes salles' | t }}</option>
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
          [titre]="'Aucune séance' | t"
          [message]="
            (periode() === 'a_venir'
              ? 'Aucune séance programmée pour le moment.'
              : 'Aucune séance passée.'
            ) | t
          "
          icone="📅"
        >
          <a class="btn btn--sm" [routerLink]="base + '/seances/nouvelle'">{{
            'Créer une séance' | t
          }}</a>
        </app-etat-vide>
      } @else {
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th scope="col">{{ 'Date' | t }}</th>
                <th scope="col">{{ 'Séance' | t }}</th>
                <th scope="col">{{ 'Lieu / coach' | t }}</th>
                <th scope="col">{{ 'Inscrits' | t }}</th>
                <th scope="col">{{ 'Prix' | t }}</th>
                <th scope="col">{{ 'Statut' | t }}</th>
                <th scope="col">
                  <span class="sr-only">{{ 'Actions' | t }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              @for (s of p.items; track s.id) {
                @let inscrits = s.capaciteMax - s.placesDisponibles;
                @let commencee = estCommencee(s);
                <tr>
                  <td>
                    {{ s.dateDebut | dateL: 'EEE d MMM y' }}<br /><span class="muted"
                      >{{ s.dateDebut | dateL: 'HH:mm' }} – {{ s.dateFin | dateL: 'HH:mm' }}</span
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
                  <td>
                    {{ +s.prix === 0 ? ('Gratuit' | t) : (s.prix | nombreL: '1.2-2') + ' €' }}
                  </td>
                  <td>
                    <span class="badge badge--{{ s.statut }}">{{
                      (s.statut === 'active' ? 'Active' : 'Annulée') | t
                    }}</span>
                  </td>
                  <td>
                    <div class="actions">
                      <a
                        class="btn btn--sm btn--ghost"
                        [routerLink]="base + '/seances/' + s.id + '/inscrits'"
                        >{{ 'Inscrits' | t }}</a
                      >
                      <!-- Séance commencée : ni modification, ni annulation, ni suppression (409/422). -->
                      @if (s.statut === 'active' && !commencee) {
                        <a
                          class="btn btn--sm btn--ghost"
                          [routerLink]="base + '/seances/' + s.id"
                          >{{ 'Modifier' | t }}</a
                        >
                        <button type="button" class="btn btn--sm btn--ghost" (click)="annuler(s)">
                          {{ 'Annuler' | t }}
                        </button>
                      }
                      @if (inscrits === 0 && !commencee) {
                        <button
                          type="button"
                          class="btn btn--sm btn--danger"
                          (click)="supprimer(s)"
                        >
                          {{ 'Supprimer' | t }}
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
  private readonly langue = inject(LangueService);

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

  /** Séance commencée ou passée (onglet « Passées » ou début atteint). */
  estCommencee(seance: SeanceResume): boolean {
    return this.periode() === 'passees' || new Date(seance.dateDebut) <= new Date();
  }

  /** @param repli page vide après une suppression ou une annulation : on recule d'une page */
  charger(page: number, repli = false): void {
    this.chargement.set(true);
    this.erreur.set('');
    this.gestion
      .seances(this.espace, { periode: this.periode(), page, salleId: this.salleId() ?? undefined })
      .subscribe({
        next: (reponse) => {
          if (repli && page > 1 && !Array.isArray(reponse) && reponse.items.length === 0) {
            this.charger(page - 1);
            return;
          }
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
          this.erreur.set(
            messageErreur(error, this.langue.t('Impossible de charger les séances.')),
          );
          this.chargement.set(false);
        },
      });
  }

  async annuler(seance: SeanceResume): Promise<void> {
    const inscrits = seance.capaciteMax - seance.placesDisponibles;
    const ok = await this.confirm.demander({
      titre: this.langue.t('Annuler « {titre} » ?', { titre: seance.titre }),
      message:
        inscrits > 1
          ? this.langue.t(
              'Les {n} sportifs inscrits seront prévenus par e-mail et leurs réservations annulées. Cette action est définitive.',
              { n: inscrits },
            )
          : inscrits === 1
            ? this.langue.t(
                'Le sportif inscrit sera prévenu par e-mail et sa réservation annulée. Cette action est définitive.',
              )
            : this.langue.t('La séance ne sera plus réservable. Cette action est définitive.'),
      confirmer: this.langue.t('Annuler la séance'),
      danger: true,
    });
    if (!ok) return;
    this.gestion.annulerSeance(seance.id).subscribe({
      next: (r) => {
        this.toasts.succes(
          this.langue.t(
            r.sportifsPrevenus === 1
              ? 'Séance annulée. {n} sportif prévenu.'
              : 'Séance annulée. {n} sportifs prévenus.',
            { n: r.sportifsPrevenus },
          ),
        );
        this.charger(this.page()?.page ?? 1, true);
      },
      error: (error) => this.toasts.erreur(messageErreur(error)),
    });
  }

  async supprimer(seance: SeanceResume): Promise<void> {
    const ok = await this.confirm.demander({
      titre: this.langue.t('Supprimer « {titre} » ?', { titre: seance.titre }),
      message: this.langue.t('La séance sera définitivement supprimée.'),
      confirmer: this.langue.t('Supprimer'),
      danger: true,
    });
    if (!ok) return;
    this.gestion.supprimerSeance(seance.id).subscribe({
      next: () => {
        this.toasts.succes(this.langue.t('Séance supprimée.'));
        this.charger(this.page()?.page ?? 1, true);
      },
      error: (error) => this.toasts.erreur(messageErreur(error)),
    });
  }
}
