import { Component, computed, inject, input } from '@angular/core';
import { LangueService } from '../../core/i18n/langue.service';
import { Disponibilite, JOURS } from '../../core/models';
import { I18N } from '../../core/i18n/pipes';

/** Créneaux de disponibilité d'une salle ou d'un coach : récurrents puis ponctuels. */
@Component({
  selector: 'app-disponibilites-liste',
  imports: [...I18N],
  template: `
    @if (creneaux().length) {
      <ul class="slots">
        @for (d of creneaux(); track d.id) {
          <li>
            <strong>{{
              d.recurrent ? chaque(d.jourSemaine) : (d.date | dateL: 'EEEE d MMMM')
            }}</strong>
            <span>{{ d.heureDebut }} – {{ d.heureFin }}</span>
          </li>
        }
      </ul>
    } @else {
      <p class="muted">{{ 'Aucun créneau communiqué pour le moment.' | t }}</p>
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
  private readonly langue = inject(LangueService);
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

  /** « Chaque lundi » / « Every Monday » (jour en minuscule en français seulement). */
  chaque(numero: number | null): string {
    const jour = this.langue.t(JOURS[numero ?? 0] ?? '');
    return this.langue.t('Chaque {jour}', {
      jour: this.langue.langue() === 'fr' ? jour.toLowerCase() : jour,
    });
  }
}
