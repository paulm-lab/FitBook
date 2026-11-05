import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { switchMap } from 'rxjs';
import { SeanceService } from '../../services/seance-service';
import { Seance } from '../../models/seance';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({ selector: 'app-seances', standalone: true, imports: [CommonModule, FormsModule, RouterLink, VisualShowcaseComponent], templateUrl: './seances.html', styleUrl: './seances.css' })
export class Seances implements OnInit {
  seances: Seance[] = [];
  erreur = '';
  chargement = true;
  activite = '';
  lieu = '';
  date = '';
  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);
  constructor(private readonly seanceService: SeanceService, private readonly route: ActivatedRoute, private readonly router: Router) {}

  ngOnInit(): void {
    this.route.queryParams.pipe(switchMap(params => {
      this.activite = params['activite'] || '';
      this.lieu = params['lieu'] || '';
      this.date = params['date'] || '';
      this.erreur = '';
      return this.seanceService.rechercherSeances(this.activite, this.lieu, this.date);
    })).subscribe({
      next: data => { this.seances = data; this.erreur = ''; this.chargement = false; this.cdr.markForCheck(); },
      error: () => { this.seances = []; this.erreur = 'Impossible de charger les séances.'; this.chargement = false; this.cdr.markForCheck(); }
    });
  }

  rechercher(): void {
    this.router.navigate([], { relativeTo: this.route, queryParams: { activite: this.activite || null, lieu: this.lieu || null, date: this.date || null } });
  }

  nomActivite(activite: Seance['activite']): string {
    return typeof activite === 'string' ? activite : activite?.nom || '';
  }

  imageSeance(seance: Seance): string {
    const activity = this.nomActivite(seance.activite).toLocaleLowerCase('fr');
    if (activity.includes('yoga') || activity.includes('pilates')) return 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72';
    if (activity.includes('box')) return 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=900&q=72';
    if (activity.includes('run') || activity.includes('course')) return 'https://images.unsplash.com/photo-1552674605-46e432e1a7ee?auto=format&fit=crop&w=900&q=72';
    return 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72';
  }

  imageError(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;
    if (!image.dataset['fallbackApplied']) { image.dataset['fallbackApplied'] = 'true'; image.src = '/assets/fitbook-media-fallback.svg'; }
  }
}
