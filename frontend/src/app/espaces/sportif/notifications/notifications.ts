import { DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { messageErreur } from '../../../core/http';
import { NotificationFitBook } from '../../../core/models';
import { CompteService } from '../../../core/services/compte.service';
import { ToastService } from '../../../core/services/toast.service';
import { EtatVideComponent, LoaderComponent } from '../../../shared/ui';

const ICONES: Record<string, string> = {
  reservation_confirmee: '✓',
  reservation_annulee: '✕',
  seance_annulee: '!',
  rappel: '⏰',
};

/** Notifications internes (bonus) : réservations, annulations, rappels. */
@Component({
  selector: 'app-notifications',
  imports: [DatePipe, LoaderComponent, EtatVideComponent],
  template: `
    <header class="page-header">
      <div>
        <span class="section-kicker">Mon espace</span>
        <h1>Notifications</h1>
        <p>
          {{ nonLues() > 0 ? nonLues() + ' notification(s) non lue(s).' : 'Vous êtes à jour.' }}
        </p>
      </div>
      @if (nonLues() > 0) {
        <button type="button" class="btn btn--ghost" [disabled]="envoi()" (click)="toutLire()">
          Tout marquer comme lu
        </button>
      }
    </header>

    @if (chargement()) {
      <app-loader />
    } @else if (items().length === 0) {
      <app-etat-vide
        titre="Aucune notification"
        message="Vos confirmations de réservation, annulations et rappels apparaîtront ici."
        icone="🔔"
      />
    } @else {
      <ul class="notif-list">
        @for (n of items(); track n.id) {
          <li [class.unread]="!n.lue">
            <span class="notif-icon" aria-hidden="true">{{ icone(n.type) }}</span>
            <div>
              <p>{{ n.message }}</p>
              <time class="muted" [attr.datetime]="n.date">{{
                n.date | date: 'EEEE d MMMM y · HH:mm'
              }}</time>
            </div>
            @if (!n.lue) {
              <span class="badge badge--primary">Nouveau</span>
            }
          </li>
        }
      </ul>
    }
  `,
  styles: `
    .notif-list {
      display: grid;
      gap: 10px;
      margin: 0;
      padding: 0;
      list-style: none;
    }
    .notif-list li {
      display: flex;
      align-items: flex-start;
      gap: 14px;
      padding: 16px;
      border: 1px solid var(--border);
      border-radius: 14px;
      background: var(--surface);
    }
    .notif-list li.unread {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px var(--primary-soft);
    }
    .notif-list li > div {
      flex: 1;
      min-width: 0;
    }
    .notif-list p {
      margin: 0 0 4px;
      color: var(--ink);
      font-weight: 600;
      overflow-wrap: anywhere;
    }
    .notif-list time {
      font-size: 13px;
    }
    .notif-icon {
      display: grid;
      place-items: center;
      flex: none;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--primary-soft);
      color: var(--primary-hover);
      font-weight: 800;
    }
  `,
})
export class NotificationsComponent {
  private readonly compte = inject(CompteService);
  private readonly toasts = inject(ToastService);

  readonly items = signal<NotificationFitBook[]>([]);
  readonly nonLues = signal(0);
  readonly chargement = signal(true);
  readonly envoi = signal(false);

  constructor() {
    this.compte.notifications().subscribe({
      next: (n) => {
        this.items.set(n.items);
        this.nonLues.set(n.nonLues);
        this.chargement.set(false);
      },
      error: (error: unknown) => {
        this.toasts.erreur(messageErreur(error, 'Les notifications n’ont pas pu être chargées.'));
        this.chargement.set(false);
      },
    });
  }

  icone(type: string): string {
    return ICONES[type] ?? 'i';
  }

  toutLire(): void {
    this.envoi.set(true);
    this.compte.marquerNotificationsLues().subscribe({
      next: () => {
        this.items.update((items) => items.map((n) => ({ ...n, lue: true })));
        this.nonLues.set(0);
        this.envoi.set(false);
      },
      error: (error: unknown) => {
        this.toasts.erreur(messageErreur(error));
        this.envoi.set(false);
      },
    });
  }
}
