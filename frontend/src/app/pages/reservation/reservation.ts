import { ChangeDetectorRef, Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Seance } from '../../models/seance';
import { SeanceService } from '../../services/seance-service';
import { ReservationService } from '../../services/reservation-service';
import { HttpErrorResponse } from '@angular/common/http';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({ selector: 'app-reservation', standalone: true, imports: [CommonModule, FormsModule, VisualShowcaseComponent], templateUrl: './reservation.html', styleUrl: '../activites/activites.css' })
export class ReservationComponent implements OnInit {
  seance?: Seance;
  nombrePlaces = 1;
  chargement = true;
  envoi = false;
  erreur = '';
  confirmation = '';
  get bookingImage(): string {
    const name = (this.seance?.titre ?? '').toLocaleLowerCase('fr');
    if (name.includes('yoga') || name.includes('pilates')) return 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?auto=format&fit=crop&w=900&q=72';
    if (name.includes('box')) return 'https://images.unsplash.com/photo-1549719386-74dfcbf7dbed?auto=format&fit=crop&w=900&q=72';
    return 'https://images.unsplash.com/photo-1534438327276-14e5300c3a48?auto=format&fit=crop&w=900&q=72';
  }
  imageError(event: Event): void { const image = event.currentTarget as HTMLImageElement; if (!image.dataset['fallbackApplied']) { image.dataset['fallbackApplied'] = 'true'; image.src = '/assets/fitbook-media-fallback.svg'; } }
  // Application zoneless : la vue doit être notifiée quand une réponse HTTP modifie l'état.
  private readonly cdr = inject(ChangeDetectorRef);
  constructor(private readonly route: ActivatedRoute, private readonly router: Router, private readonly seances: SeanceService, private readonly reservations: ReservationService) {}
  ngOnInit(): void {
    const id = Number(this.route.snapshot.queryParamMap.get('seance'));
    if (!Number.isInteger(id) || id < 1) { this.erreur = 'La séance sélectionnée est invalide.'; this.chargement = false; return; }
    this.seances.getSeance(id).subscribe({ next: seance => { this.seance = seance; this.chargement = false; this.cdr.markForCheck(); }, error: error => { this.chargement = false; this.erreur = error.status === 404 ? 'Cette séance n’existe plus.' : 'Impossible de charger la séance.'; this.cdr.markForCheck(); } });
  }
  reserver(): void {
    if (!this.seance || this.nombrePlaces < 1 || this.nombrePlaces > this.seance.placesDisponibles) return;
    this.envoi = true; this.erreur = '';
    this.reservations.reserver(this.seance.id, this.nombrePlaces).subscribe({
      next: data => { this.envoi = false; this.confirmation = `Réservation confirmée (${data.nombrePlaces} place${data.nombrePlaces > 1 ? 's' : ''}). Montant : ${data.montantTotal} €.`; this.cdr.markForCheck(); },
      error: (error: HttpErrorResponse) => {
        this.envoi = false;
        if (error.status === 401) { void this.router.navigate(['/connexion'], { queryParams: { returnUrl: this.router.url } }); return; }
        this.erreur = error.status === 409 ? 'Vous avez déjà réservé cette séance ou il ne reste plus assez de places.' : error.status === 404 ? 'Cette séance n’existe plus.' : 'Réservation impossible. Réessayez plus tard.';
        this.cdr.markForCheck();
      }
    });
  }
}
