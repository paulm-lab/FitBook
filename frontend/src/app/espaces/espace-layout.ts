import { Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { map } from 'rxjs';
import { AuthService } from '../core/auth/auth.service';
import { ROLES } from '../core/models';
import { ImageSecoursDirective } from '../shared/ui';

export interface LienEspace {
  chemin: string;
  libelle: string;
  exact?: boolean;
}

/**
 * Mise en page commune des espaces (sportif, coach, gestionnaire, admin) : menu latéral
 * défini dans les données de la route (app.routes.ts), contenu à droite.
 * Sur mobile, le menu devient une barre d'onglets horizontale défilante.
 */
@Component({
  selector: 'app-espace-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, ImageSecoursDirective],
  template: `
    <div class="space">
      <aside class="space-nav" [attr.aria-label]="titre()">
        <div class="space-user">
          @if (auth.utilisateur()?.avatar) {
            <img class="avatar" [src]="auth.utilisateur()!.avatar" alt="" appSecours />
          } @else {
            <span class="avatar avatar--initiales" aria-hidden="true">{{ initiales() }}</span>
          }
          <div>
            <strong>{{ auth.utilisateur()?.prenom }} {{ auth.utilisateur()?.nom }}</strong>
            <span class="space-role">{{ roleLibelle() }}</span>
          </div>
        </div>
        <p class="space-title">{{ titre() }}</p>
        <nav>
          @for (lien of liens(); track lien.chemin) {
            <a
              [routerLink]="lien.chemin"
              routerLinkActive="active"
              [routerLinkActiveOptions]="{ exact: !!lien.exact }"
              >{{ lien.libelle }}</a
            >
          }
        </nav>
      </aside>
      <main class="space-content" id="contenu">
        <router-outlet />
      </main>
    </div>
  `,
})
export class EspaceLayoutComponent {
  readonly auth = inject(AuthService);
  private readonly route = inject(ActivatedRoute);

  readonly titre = toSignal(
    this.route.data.pipe(map((d) => (d['titre'] as string) ?? 'Mon espace')),
    { initialValue: '' },
  );
  readonly liens = toSignal(this.route.data.pipe(map((d) => (d['liens'] as LienEspace[]) ?? [])), {
    initialValue: [],
  });

  initiales(): string {
    const u = this.auth.utilisateur();
    return `${u?.prenom?.[0] ?? ''}${u?.nom?.[0] ?? ''}`.toUpperCase();
  }

  roleLibelle(): string {
    const role = this.auth.role();
    return role ? ROLES[role] : '';
  }
}
