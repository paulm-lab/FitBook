import { DOCUMENT } from '@angular/common';
import { Component, effect, inject, signal } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter } from 'rxjs';
import { AuthService } from './core/auth/auth.service';
import { CompteService } from './core/services/compte.service';
import { ThemeService } from './core/services/theme.service';
import { ToastService } from './core/services/toast.service';
import { ConfirmDialogComponent, ImageSecoursDirective, ToastsComponent } from './shared/ui';
import { FITBOOK_VIDEO_SOURCES } from './video-sources';

/** Coquille de l'application : en-tête, contenu routé, pied de page, toasts et confirmations. */
@Component({
  selector: 'app-root',
  imports: [RouterOutlet, RouterLink, RouterLinkActive, ToastsComponent, ConfirmDialogComponent, ImageSecoursDirective],
  templateUrl: './app.html',
  styleUrl: './app.css',
})
export class AppComponent {
  readonly auth = inject(AuthService);
  readonly theme = inject(ThemeService);
  private readonly compte = inject(CompteService);
  readonly nonLues = this.compte.nonLues;
  private readonly toasts = inject(ToastService);
  private readonly router = inject(Router);
  private readonly document = inject(DOCUMENT);

  readonly menuOuvert = signal(false);
  readonly annee = new Date().getFullYear();

  constructor() {
    this.router.events.pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd)).subscribe(() => {
      this.menuOuvert.set(false);
      setTimeout(() => this.reparerLecteursVideo(), 0);
      this.chargerNotifications();
    });

    // Compteur de notifications rechargé à la connexion.
    effect(() => {
      if (this.auth.estConnecte()) {
        this.chargerNotifications();
      } else {
        this.compte.nonLues.set(0);
      }
    });
  }

  deconnecter(): void {
    this.auth.deconnexion().subscribe(() => {
      this.toasts.info('Vous êtes déconnecté. À bientôt !');
      void this.router.navigateByUrl('/');
    });
  }

  initiales(): string {
    const u = this.auth.utilisateur();
    return `${u?.prenom?.[0] ?? ''}${u?.nom?.[0] ?? ''}`.toUpperCase();
  }

  private chargerNotifications(): void {
    if (!this.auth.estConnecte()) return;
    this.compte.notifications().subscribe({ error: () => this.compte.nonLues.set(0) });
  }

  /** Remplace la source des vidéos par la liste de secours (CDN Pexels indisponible). */
  private reparerLecteursVideo(): void {
    this.document.querySelectorAll<HTMLVideoElement>('video').forEach(video => {
      if (video.dataset['fitbookSourcesReparees'] === 'true') return;

      video.replaceChildren(...FITBOOK_VIDEO_SOURCES.map(url => {
        const source = this.document.createElement('source');
        source.src = url;
        source.type = 'video/mp4';
        return source;
      }));
      video.dataset['fitbookSourcesReparees'] = 'true';
      video.preload = 'metadata';
      video.load();
    });
  }
}
