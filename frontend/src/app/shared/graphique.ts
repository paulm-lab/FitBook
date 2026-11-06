import {
  AfterViewInit,
  Component,
  ElementRef,
  OnDestroy,
  effect,
  inject,
  input,
  viewChild,
} from '@angular/core';
import {
  BarController,
  BarElement,
  CategoryScale,
  Chart,
  Filler,
  Legend,
  LineController,
  LineElement,
  LinearScale,
  PointElement,
  Tooltip,
} from 'chart.js';
import { LangueService } from '../core/i18n/langue.service';
import { Evolution } from '../core/models';

Chart.register(
  LineController,
  LineElement,
  PointElement,
  BarController,
  BarElement,
  CategoryScale,
  LinearScale,
  Filler,
  Tooltip,
  Legend,
);

/**
 * Évolution hebdomadaire des réservations (Chart.js), pour les tableaux de bord (US-G05).
 * Les couleurs suivent la charte : orange FitBook pour la série, texte lu dans les jetons CSS.
 */
@Component({
  selector: 'app-graphique-evolution',
  template: `
    <figure class="chart">
      <div class="chart-canvas">
        <canvas #canvas role="img" [attr.aria-label]="description()"></canvas>
      </div>
      <figcaption class="sr-only">{{ description() }}</figcaption>
    </figure>
  `,
})
export class GraphiqueEvolutionComponent implements AfterViewInit, OnDestroy {
  readonly donnees = input.required<Evolution[]>();
  readonly libelle = input('Réservations par semaine');

  private readonly langue = inject(LangueService);
  private readonly canvas = viewChild.required<ElementRef<HTMLCanvasElement>>('canvas');
  private chart?: Chart;

  constructor() {
    effect(() => {
      const donnees = this.donnees();
      if (this.chart) {
        this.chart.data.labels = donnees.map((d) => this.semaine(d.semaine));
        this.chart.data.datasets[0].data = donnees.map((d) => d.reservations);
        this.chart.data.datasets[0].label = this.langue.t(this.libelle());
        this.chart.update();
      }
    });
  }

  description(): string {
    const total = this.donnees().reduce((somme, d) => somme + d.reservations, 0);
    return this.langue.t('{libelle} : {total} réservations sur {semaines} semaines.', {
      libelle: this.langue.t(this.libelle()),
      total,
      semaines: this.donnees().length,
    });
  }

  ngAfterViewInit(): void {
    const styles = getComputedStyle(document.documentElement);
    const texte = styles.getPropertyValue('--text').trim() || '#536174';
    const grille = styles.getPropertyValue('--border').trim() || '#e1e5eb';

    this.chart = new Chart(this.canvas().nativeElement, {
      type: 'bar',
      data: {
        labels: this.donnees().map((d) => this.semaine(d.semaine)),
        datasets: [
          {
            label: this.langue.t(this.libelle()),
            data: this.donnees().map((d) => d.reservations),
            backgroundColor: '#ff642d',
            hoverBackgroundColor: '#e84e1e',
            borderRadius: 6,
            maxBarThickness: 36,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              title: (items) => this.langue.t('Semaine du {date}', { date: items[0].label }),
            },
          },
        },
        scales: {
          x: { ticks: { color: texte }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { color: texte, precision: 0 }, grid: { color: grille } },
        },
      },
    });
  }

  ngOnDestroy(): void {
    this.chart?.destroy();
  }

  private semaine(lundi: string): string {
    const [, mois, jour] = lundi.split('-');
    return `${jour}/${mois}`;
  }
}
