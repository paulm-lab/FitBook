import { Injectable, signal } from '@angular/core';

export type TypeToast = 'succes' | 'erreur' | 'info';

export interface Toast {
  id: number;
  type: TypeToast;
  message: string;
}

/** Messages éphémères affichés en bas d'écran (composant <app-toasts>, région aria-live). */
@Injectable({ providedIn: 'root' })
export class ToastService {
  private prochainId = 1;
  private readonly liste = signal<Toast[]>([]);
  readonly toasts = this.liste.asReadonly();

  succes(message: string): void {
    this.afficher('succes', message);
  }

  erreur(message: string): void {
    this.afficher('erreur', message, 7000);
  }

  info(message: string): void {
    this.afficher('info', message);
  }

  fermer(id: number): void {
    this.liste.update((toasts) => toasts.filter((t) => t.id !== id));
  }

  private afficher(type: TypeToast, message: string, duree = 4500): void {
    const id = this.prochainId++;
    // Au plus 4 messages à l'écran : les plus anciens disparaissent.
    this.liste.update((toasts) => [...toasts.slice(-3), { id, type, message }]);
    setTimeout(() => this.fermer(id), duree);
  }
}
