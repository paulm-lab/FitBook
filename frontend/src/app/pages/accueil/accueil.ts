import { Component } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';

@Component({
  selector: 'app-accueil',
  standalone: true,
  imports: [RouterLink, FormsModule, VisualShowcaseComponent],
  templateUrl: './accueil.html',
  styleUrl: './accueil.css'
})
export class AccueilComponent {
  activite = '';
  lieu = 'Paris, France';
  quand = 'Cette semaine';

  constructor(private router: Router) {}

  rechercher(event: Event): void {
    event.preventDefault();

    const today = new Date();
    let date: string | null = null;
    if (this.quand === 'Cette semaine') {
      date = this.formatDate(today);
    } else if (this.quand === 'Ce week-end') {
      const daysUntilSaturday = (6 - today.getDay() + 7) % 7;
      const saturday = new Date(today);
      saturday.setDate(today.getDate() + daysUntilSaturday);
      date = this.formatDate(saturday);
    } else if (this.quand === 'La semaine prochaine') {
      const monday = new Date(today);
      monday.setDate(today.getDate() + ((8 - today.getDay()) % 7 || 7));
      date = this.formatDate(monday);
    }

    this.router.navigate(['/seances'], {
      queryParams: {
        activite: this.activite.trim() || null,
        lieu: this.lieu.trim() || null,
        date
      }
    });
  }

  private formatDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  imageError(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;
    if (!image.dataset['fallbackApplied']) { image.dataset['fallbackApplied'] = 'true'; image.src = '/assets/fitbook-media-fallback.svg'; }
  }
}
