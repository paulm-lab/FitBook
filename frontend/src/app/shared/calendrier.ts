import { Component, computed, input, output } from '@angular/core';
import { FullCalendarModule } from '@fullcalendar/angular';
import { CalendarOptions, DatesSetArg, EventClickArg, EventInput } from '@fullcalendar/core';
import frLocale from '@fullcalendar/core/locales/fr';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin, { DateClickArg } from '@fullcalendar/interaction';
import listPlugin from '@fullcalendar/list';
import timeGridPlugin from '@fullcalendar/timegrid';
import { Disponibilite, SeanceResume } from '../core/models';

/** Séance → évènement FullCalendar (couleur selon le remplissage ou l'annulation). */
export function evenementSeance(seance: SeanceResume): EventInput {
  const annulee = seance.statut === 'annulee';
  const complet = seance.placesDisponibles <= 0;
  return {
    id: `seance-${seance.id}`,
    title: `${seance.titre} (${seance.capaciteMax - seance.placesDisponibles}/${seance.capaciteMax})`,
    start: seance.dateDebut,
    end: seance.dateFin,
    classNames: [annulee ? 'fc-ev--annulee' : complet ? 'fc-ev--complet' : 'fc-ev--seance'],
    extendedProps: { seance },
  };
}

/**
 * Disponibilités → évènements d'arrière-plan : créneaux récurrents (daysOfWeek) ou ponctuels.
 * FullCalendar numérote les jours de 0 (dimanche) à 6 ; l'API de 1 (lundi) à 7.
 */
export function evenementsDisponibilites(disponibilites: Disponibilite[]): EventInput[] {
  return disponibilites.map((d) => ({
    id: `dispo-${d.id}`,
    display: 'background',
    classNames: ['fc-ev--dispo'],
    ...(d.recurrent
      ? { daysOfWeek: [(d.jourSemaine ?? 1) % 7], startTime: d.heureDebut, endTime: d.heureFin }
      : { start: `${d.date}T${d.heureDebut}`, end: `${d.date}T${d.heureFin}` }),
  }));
}

/** Calendrier FullCalendar en français (semaine, mois, liste), utilisé par les espaces. */
@Component({
  selector: 'app-calendrier',
  imports: [FullCalendarModule],
  template: `<div class="calendar-wrap"><full-calendar [options]="options()" /></div>`,
})
export class CalendrierComponent {
  readonly evenements = input<EventInput[]>([]);
  readonly vue = input<'timeGridWeek' | 'dayGridMonth' | 'listWeek'>('timeGridWeek');
  /** Nouvelle fenêtre affichée (pour charger les séances de la période). */
  readonly periode = output<{ debut: Date; fin: Date }>();
  readonly seanceCliquee = output<SeanceResume>();
  readonly jourClique = output<Date>();

  readonly options = computed<CalendarOptions>(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    locale: frLocale,
    initialView: this.vue(),
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'timeGridWeek,dayGridMonth,listWeek',
    },
    slotMinTime: '06:00:00',
    slotMaxTime: '23:00:00',
    allDaySlot: false,
    nowIndicator: true,
    height: 'auto',
    events: this.evenements(),
    datesSet: (arg: DatesSetArg) => this.periode.emit({ debut: arg.start, fin: arg.end }),
    eventClick: (arg: EventClickArg) => {
      const seance = arg.event.extendedProps['seance'] as SeanceResume | undefined;
      if (seance) this.seanceCliquee.emit(seance);
    },
    dateClick: (arg: DateClickArg) => this.jourClique.emit(arg.date),
  }));
}
