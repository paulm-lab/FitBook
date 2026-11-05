import { DatePipe } from '@angular/common';
import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { AuthService } from '../../../core/auth/auth.service';
import { messageErreur } from '../../../core/http';
import { Page, ROLES, Role, UtilisateurAdmin } from '../../../core/models';
import { GestionService } from '../../../core/services/gestion.service';
import { ToastService } from '../../../core/services/toast.service';
import {
  ConfirmService,
  EtatVideComponent,
  LoaderComponent,
  PaginationComponent,
} from '../../../shared/ui';

/** Gestion des comptes (US-A02) : recherche, rôle, vérification, blocage, suppression. */
@Component({
  selector: 'app-utilisateurs',
  imports: [DatePipe, LoaderComponent, EtatVideComponent, PaginationComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Administration</span>
        <h1>Utilisateurs</h1>
        <p>Recherchez un compte, changez son rôle ou bloquez-le.</p>
      </div>
    </header>

    <div class="row filters">
      <label class="field grow">
        <span>Rechercher</span>
        <input
          type="search"
          class="input"
          placeholder="Nom, prénom ou e-mail"
          [value]="q()"
          (input)="saisie$.next($any($event.target).value)"
        />
      </label>
      <label class="field">
        <span>Rôle</span>
        <select class="input" [value]="role()" (change)="filtrerRole($any($event.target).value)">
          <option value="">Tous les rôles</option>
          @for (r of roles; track r.code) {
            <option [value]="r.code">{{ r.libelle }}</option>
          }
        </select>
      </label>
    </div>

    @if (erreur()) {
      <p class="alert alert--error" role="alert">{{ erreur() }}</p>
    } @else if (!page()) {
      <app-loader />
    } @else if (page()!.items.length === 0) {
      <app-etat-vide
        titre="Aucun utilisateur"
        message="Aucun compte ne correspond à ces critères."
      />
    } @else {
      <p class="muted">{{ page()!.total }} compte(s)</p>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th scope="col">Nom</th>
              <th scope="col">E-mail</th>
              <th scope="col">Rôle</th>
              <th scope="col">État</th>
              <th scope="col">Inscription</th>
              <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @for (u of page()!.items; track u.id) {
              @let soi = u.id === moi();
              <tr>
                <td>
                  <strong>{{ u.prenom }} {{ u.nom }}</strong>
                  @if (soi) {
                    <span class="badge badge--primary">Vous</span>
                  }
                </td>
                <td>{{ u.email }}</td>
                <td>
                  <select
                    class="input input--sm"
                    [attr.aria-label]="'Rôle de ' + u.prenom + ' ' + u.nom"
                    [value]="u.role"
                    [disabled]="soi || enCours() === u.id"
                    (change)="changerRole(u, $any($event.target))"
                  >
                    @for (r of roles; track r.code) {
                      <option [value]="r.code" [selected]="r.code === u.role">
                        {{ r.libelle }}
                      </option>
                    }
                  </select>
                </td>
                <td>
                  <div class="chips">
                    @if (u.estVerifie) {
                      <span class="badge badge--ok">Vérifié</span>
                    } @else {
                      <span class="badge">Non vérifié</span>
                    }
                    @if (u.estBloque) {
                      <span class="badge badge--full">Bloqué</span>
                    }
                  </div>
                </td>
                <td>{{ u.dateInscription | date: 'd MMM y' }}</td>
                <td>
                  <div class="actions">
                    @if (!u.estVerifie) {
                      <button
                        type="button"
                        class="btn btn--sm btn--ghost"
                        [disabled]="enCours() === u.id"
                        (click)="verifier(u)"
                      >
                        Vérifier
                      </button>
                    }
                    <button
                      type="button"
                      class="btn btn--sm btn--ghost"
                      [disabled]="soi || enCours() === u.id"
                      (click)="basculerBlocage(u)"
                    >
                      {{ u.estBloque ? 'Débloquer' : 'Bloquer' }}
                    </button>
                    <button
                      type="button"
                      class="btn btn--sm btn--danger"
                      [disabled]="soi || enCours() === u.id"
                      (click)="supprimer(u)"
                    >
                      Supprimer
                    </button>
                  </div>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
      <app-pagination [page]="page()!.page" [pages]="page()!.pages" (changer)="charger($event)" />
    }
  `,
  styles: `
    .filters {
      align-items: end;
      margin-bottom: 18px;
    }
    .grow {
      flex: 1;
      min-width: 220px;
    }
    .input--sm {
      min-height: 36px;
      padding: 6px 10px;
    }
    td .badge {
      margin-left: 6px;
    }
  `,
})
export class UtilisateursComponent {
  private readonly gestion = inject(GestionService);
  private readonly toasts = inject(ToastService);
  private readonly confirm = inject(ConfirmService);
  private readonly auth = inject(AuthService);

  readonly roles = (Object.keys(ROLES) as Role[]).map((code) => ({ code, libelle: ROLES[code] }));
  readonly q = signal('');
  readonly role = signal('');
  readonly page = signal<Page<UtilisateurAdmin> | null>(null);
  readonly erreur = signal('');
  readonly enCours = signal<number | null>(null);
  readonly saisie$ = new Subject<string>();

  moi(): number | undefined {
    return this.auth.utilisateur()?.id;
  }

  constructor() {
    this.saisie$
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed(inject(DestroyRef)))
      .subscribe((q) => {
        this.q.set(q.trim());
        this.charger(1);
      });
    this.charger(1);
  }

  filtrerRole(role: string): void {
    this.role.set(role);
    this.charger(1);
  }

  charger(numero: number): void {
    this.erreur.set('');
    this.gestion.utilisateurs({ q: this.q(), role: this.role(), page: numero }).subscribe({
      next: (p) => this.page.set(p),
      error: (e) => this.erreur.set(messageErreur(e, 'Impossible de charger les utilisateurs.')),
    });
  }

  async changerRole(u: UtilisateurAdmin, select: HTMLSelectElement): Promise<void> {
    const role = select.value as Role;
    const ok = await this.confirm.demander({
      titre: 'Changer le rôle',
      message: `${u.prenom} ${u.nom} deviendra « ${ROLES[role]} ». Ses accès changeront à sa prochaine connexion.`,
      confirmer: 'Changer le rôle',
    });
    if (!ok) {
      select.value = u.role;
      return;
    }
    this.modifier(u, { role }, 'Rôle modifié.', () => (select.value = u.role));
  }

  verifier(u: UtilisateurAdmin): void {
    this.modifier(u, { estVerifie: true }, 'Adresse marquée comme vérifiée.');
  }

  async basculerBlocage(u: UtilisateurAdmin): Promise<void> {
    const bloquer = !u.estBloque;
    const ok = await this.confirm.demander({
      titre: bloquer ? 'Bloquer le compte' : 'Débloquer le compte',
      message: bloquer
        ? `${u.prenom} ${u.nom} ne pourra plus se connecter.`
        : `${u.prenom} ${u.nom} pourra de nouveau se connecter.`,
      confirmer: bloquer ? 'Bloquer' : 'Débloquer',
      danger: bloquer,
    });
    if (ok)
      this.modifier(u, { estBloque: bloquer }, bloquer ? 'Compte bloqué.' : 'Compte débloqué.');
  }

  async supprimer(u: UtilisateurAdmin): Promise<void> {
    const ok = await this.confirm.demander({
      titre: 'Supprimer le compte',
      message: `Le compte de ${u.prenom} ${u.nom} et ses réservations, favoris et avis seront supprimés définitivement.`,
      confirmer: 'Supprimer',
      danger: true,
    });
    if (!ok) return;
    this.enCours.set(u.id);
    this.gestion.supprimerUtilisateur(u.id).subscribe({
      next: () => {
        this.enCours.set(null);
        this.toasts.succes('Compte supprimé.');
        this.charger(this.page()?.page ?? 1);
      },
      error: (e) => {
        this.enCours.set(null);
        this.toasts.erreur(messageErreur(e));
      },
    });
  }

  private modifier(
    u: UtilisateurAdmin,
    data: { role?: string; estBloque?: boolean; estVerifie?: boolean },
    succes: string,
    annuler?: () => void,
  ): void {
    this.enCours.set(u.id);
    this.gestion.modifierUtilisateur(u.id, data).subscribe({
      next: (maj) => {
        this.enCours.set(null);
        this.page.update((p) =>
          p ? { ...p, items: p.items.map((i) => (i.id === maj.id ? maj : i)) } : p,
        );
        this.toasts.succes(succes);
      },
      error: (e) => {
        this.enCours.set(null);
        annuler?.();
        this.toasts.erreur(messageErreur(e));
      },
    });
  }
}
