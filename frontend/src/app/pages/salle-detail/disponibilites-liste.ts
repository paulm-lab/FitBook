import { DatePipe } from '@angular/common';
import { Component, computed, input } from '@angular/core';
import { Disponibilite, JOURS } from '../../core/models';

/** Créneaux de disponibilité d'une salle ou d'un coach : récurrents puis ponctuels. */
@Component({
  selector: 'app-disponibilites-liste',
  imports: [DatePipe],
  template: `
    @if (creneaux().length) {
      <ul class="slots">
        @for (d of creneaux(); track d.id) {
          <li>
            <strong>{{
              d.recurrent
                ? 'Chaque ' + jour(d.jourSemaine).toLowerCase()
                : (d.date | date: 'EEEE d MMMM')
            }}</strong>
            <span>{{ d.heureDebut }} – {{ d.heureFin }}</span>
          </li>
        }
      </ul>
    } @else {
      <p class="muted">Aucun créneau communiqué pour le moment.</p>
    }
  `,
  styles: `
    .slots {
      display: grid;
      gap: 8px;
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .slots li {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 10px 14px;
      border-radius: 10px;
      background: var(--surface-alt);
    }
    .slots strong {
      color: var(--ink);
    }
    .slots span {
      color: var(--text);
      white-space: nowrap;
    }
  `,
})
export class DisponibilitesListeComponent {
  readonly disponibilites = input<Disponibilite[]>([]);

  /** Récurrents (lundi → dimanche), puis ponctuels à venir par date. */
  readonly creneaux = computed(() => {
    const aujourdhui = new Date().toISOString().slice(0, 10);
    return [...this.disponibilites()]
      .filter((d) => d.recurrent || (d.date ?? '') >= aujourdhui)
      .sort(
        (a, b) =>
          Number(b.recurrent) - Number(a.recurrent) ||
          (a.jourSemaine ?? 0) - (b.jourSemaine ?? 0) ||
          (a.date ?? '').localeCompare(b.date ?? '') ||
          a.heureDebut.localeCompare(b.heureDebut),
      );
  });

  jour(numero: number | null): string {
    return JOURS[numero ?? 0] ?? '';
  }
}
