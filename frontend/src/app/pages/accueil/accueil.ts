import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { VisualShowcaseComponent } from '../../components/visual-showcase/visual-showcase';
import { I18N } from '../../core/i18n/pipes';

type Quand = 'Cette semaine' | 'Ce week-end' | 'La semaine prochaine' | "N'importe quand";

/** Accueil : barre de recherche « quoi / où / quand » vers /recherche (US-V01). */
@Component({
  selector: 'app-accueil',
  imports: [RouterLink, FormsModule, VisualShowcaseComponent, ...I18N],
  templateUrl: './accueil.html',
  styleUrl: './accueil.css',
})
export class AccueilComponent {
  private readonly router = inject(Router);

  // Liées par ngModel (formulaire simple sans validation).
  quoi = '';
  lieu = '';
  quand: Quand = 'Cette semaine';

  rechercher(event: Event): void {
    event.preventDefault();
    const { du, au } = this.periode(this.quand, new Date());

    void this.router.navigate(['/recherche'], {
      queryParams: { q: this.quoi.trim() || null, ville: this.lieu.trim() || null, du, au },
    });
  }

  /** Période choisie → dates du/au (AAAA-MM-JJ, heure locale), null pour « n'importe quand ». */
  periode(quand: Quand, aujourdhui: Date): { du: string | null; au: string | null } {
    const decaler = (jours: number) => {
      const date = new Date(aujourdhui);
      date.setDate(aujourdhui.getDate() + jours);
      return this.formater(date);
    };
    // getDay() : 0 = dimanche … 6 = samedi.
    const jour = aujourdhui.getDay();
    const jusquADimanche = (7 - jour) % 7;

    switch (quand) {
      case 'Cette semaine':
        return { du: decaler(0), au: decaler(jusquADimanche) };
      case 'Ce week-end': {
        const samedi = jour === 0 ? -1 : 6 - jour;
        return { du: decaler(Math.max(0, samedi)), au: decaler(jusquADimanche) };
      }
      case 'La semaine prochaine':
        return { du: decaler(jusquADimanche + 1), au: decaler(jusquADimanche + 7) };
      default:
        return { du: null, au: null };
    }
  }

  imageError(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;
    if (!image.dataset['fallbackApplied']) {
      image.dataset['fallbackApplied'] = 'true';
      image.src = '/assets/fitbook-media-fallback.svg';
    }
  }

  private formater(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }
}
