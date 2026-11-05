import { Component } from '@angular/core';
import { Router } from '@angular/router';

interface TarifPlan {
  id: string;
  nom: string;
  prix: number;
  seances: string;
  avantages: string[];
  featured?: boolean;
}

@Component({
  selector: 'app-tarifs',
  standalone: true,
  templateUrl: './tarifs.html',
  styleUrl: './tarifs.css',
})
export class TarifsComponent {
  readonly plans: TarifPlan[] = [
    {
      id: 'decouverte',
      nom: 'Découverte',
      prix: 29,
      seances: '3 séances par mois',
      avantages: ['Réservation instantanée', 'Sans engagement', 'Suivi de progression'],
    },
    {
      id: 'essentiel',
      nom: 'Essentiel',
      prix: 59,
      seances: '8 séances par mois',
      avantages: ['Réservation instantanée', 'Sans engagement', 'Suivi de progression'],
      featured: true,
    },
    {
      id: 'illimite',
      nom: 'Illimité',
      prix: 89,
      seances: 'Séances en illimité',
      avantages: ['Réservation instantanée', 'Sans engagement', 'Suivi de progression'],
    },
  ];

  constructor(private readonly router: Router) {}

  choisirFormule(formule: string): void {
    void this.router.navigate(['/inscription'], { queryParams: { formule } });
  }
}
