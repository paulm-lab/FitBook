import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CatalogueService } from '../../services/catalogue-service';
import { Coach, Salle } from '../../models/coach-salle';
import { ActivatedRoute } from '@angular/router';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({ selector: 'app-coaches-salles', standalone: true, imports: [CommonModule, VisualShowcaseComponent], templateUrl: './coaches-salles.html', styleUrl: './coaches-salles.css' })
export class CoachesSallesComponent implements OnInit {
  coaches: Coach[] = [];
  salles: Salle[] = [];
  coachErreur = '';
  salleErreur = '';
  coachesChargement = true;
  sallesChargement = true;
  montrerCoaches = true;
  montrerSalles = true;
  pageVisuels: 'coaches' | 'rooms' = 'coaches';
  constructor(private readonly catalogue: CatalogueService, private readonly route: ActivatedRoute) {}
  ngOnInit(): void {
    const section = this.route.snapshot.data['section'];
    this.montrerCoaches = section !== 'salles';
    this.montrerSalles = section !== 'coaches';
    this.pageVisuels = section === 'salles' ? 'rooms' : 'coaches';
    this.catalogue.getCoaches().subscribe({next: data => { this.coaches = data; this.coachesChargement = false; }, error: () => { this.coachErreur = 'Impossible de charger les coaches.'; this.coachesChargement = false; }});
    this.catalogue.getSalles().subscribe({next: data => { this.salles = data; this.sallesChargement = false; }, error: () => { this.salleErreur = 'Impossible de charger les salles.'; this.sallesChargement = false; }});
  }

  photoCoach(coach: Coach): string { return coach.photo || 'https://images.unsplash.com/photo-1567013127542-490d757e51fc?auto=format&fit=crop&w=850&q=72'; }
  photoSalle(salle: Salle): string { return salle.image || 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=850&q=72'; }
  imageError(event: Event): void { const image = event.currentTarget as HTMLImageElement; if (!image.dataset['fallbackApplied']) { image.dataset['fallbackApplied'] = 'true'; image.src = '/assets/fitbook-media-fallback.svg'; } }
}
