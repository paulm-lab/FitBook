import { Component, inject } from '@angular/core';
import { AsyncPipe } from '@angular/common';
import { DOCUMENT } from '@angular/common';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { EMPTY, filter, catchError } from 'rxjs';
import { FITBOOK_VIDEO_SOURCES } from './video-sources';
import { AuthService } from './services/auth-service';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, AsyncPipe],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class AppComponent {
  menuOuvert = false;
  readonly auth = inject(AuthService);
  private readonly document = inject(DOCUMENT);

  constructor(router: Router) {
    this.auth.verifierSession().pipe(catchError(() => EMPTY)).subscribe();
    router.events.pipe(filter((event): event is NavigationEnd => event instanceof NavigationEnd)).subscribe(() => {
      setTimeout(() => this.reparerLecteursVideo(), 0);
    });
    setTimeout(() => this.reparerLecteursVideo(), 0);
  }

  fermerMenu(): void {
    this.menuOuvert = false;
  }

  deconnecter(): void {
    this.auth.deconnexion().subscribe();
    this.fermerMenu();
  }

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
