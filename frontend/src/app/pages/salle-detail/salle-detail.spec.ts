import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Disponibilite } from '../../core/models';
import { CoachDetailComponent } from '../coach-detail/coach-detail';
import { DisponibilitesListeComponent } from './disponibilites-liste';
import { SalleDetailComponent } from './salle-detail';

function ponctuel(id: number, date: string): Disponibilite {
  return {
    id,
    coachId: 1,
    salleId: null,
    recurrent: false,
    jourSemaine: null,
    date,
    heureDebut: '10:00',
    heureFin: '11:00',
  };
}

describe('DisponibilitesListeComponent', () => {
  afterEach(() => vi.useRealTimers());

  it('masque les créneaux ponctuels de la veille d’après la date locale', () => {
    // 0 h 30 heure locale le 7 novembre : en UTC (Bruxelles), on serait encore le 6.
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 10, 7, 0, 30));
    const fixture = TestBed.createComponent(DisponibilitesListeComponent);
    fixture.componentRef.setInput('disponibilites', [
      ponctuel(1, '2026-11-06'),
      ponctuel(2, '2026-11-07'),
    ]);
    expect(fixture.componentInstance.creneaux().map((d) => d.id)).toEqual([2]);
  });
});

describe('fiches publiques : changement d’identifiant', () => {
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
  });

  it('annule la requête de la salle précédente', async () => {
    const http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(SalleDetailComponent);
    fixture.componentRef.setInput('id', '1');
    await fixture.whenStable();
    const premiere = http.expectOne('/api/salles/1');
    fixture.componentRef.setInput('id', '2');
    await fixture.whenStable();
    http.expectOne('/api/salles/2');
    expect(premiere.cancelled).toBe(true);
    expect(fixture.componentInstance.etat()).toBe('chargement');
  });

  it('annule la requête du coach précédent', async () => {
    const http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(CoachDetailComponent);
    fixture.componentRef.setInput('id', '1');
    await fixture.whenStable();
    const premiere = http.expectOne('/api/coaches/1');
    fixture.componentRef.setInput('id', '2');
    await fixture.whenStable();
    http.expectOne('/api/coaches/2');
    expect(premiere.cancelled).toBe(true);
  });
});
