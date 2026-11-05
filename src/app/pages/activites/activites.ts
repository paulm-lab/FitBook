import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { CatalogueService } from '../../services/catalogue-service';
import { Activite } from '../../models/activite';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({ selector: 'app-activites', standalone: true, imports: [CommonModule, FormsModule, RouterLink, VisualShowcaseComponent], templateUrl: './activites.html', styleUrl: './activites.css' })
export class ActivitesComponent implements OnInit {
  activites: Activite[] = [];
  recherche = '';
  erreur = '';
  loading = true;
  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);
  constructor(private readonly catalogue: CatalogueService) {}
  ngOnInit(): void {
    this.catalogue.getActivites().subscribe({next: data => { this.activites = data; this.loading = false; this.cdr.markForCheck(); }, error: () => { this.erreur = 'Impossible de charger les activités.'; this.loading = false; this.cdr.markForCheck(); }});
  }
  get activitesFiltrees(): Activite[] {
    const terme = this.recherche.trim().toLocaleLowerCase('fr');
    return this.activites.filter(item => `${item.nom} ${item.description} ${item.niveau}`.toLocaleLowerCase('fr').includes(terme));
  }

  imageActivite(activite: Activite): string {
    if (activite.image) return activite.image;
    const name = activite.nom.toLocaleLowerCase('fr');
    if (name.includes('yoga') || name.includes('pilates')) return 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72';
    if (name.includes('box')) return 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=900&q=72';
    return 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72';
  }

  imageError(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;
    if (!image.dataset['fallbackApplied']) { image.dataset['fallbackApplied'] = 'true'; image.src = '/assets/fitbook-media-fallback.svg'; }
  }
}
