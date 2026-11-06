import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Reservation, StatutReservation } from '../../core/models';
import { Inscrits } from '../../core/services/gestion.service';
import { InscritsComponent } from './inscrits/inscrits';

function reservation(id: number, statut: StatutReservation): Reservation {
  return {
    id,
    statut,
    dateReservation: '2026-11-01T10:00:00+01:00',
    dateAnnulation: null,
    annulable: false,
    qrToken: null,
    seance: null,
  };
}

describe('InscritsComponent', () => {
  it('« Confirmés seulement » garde les participants d’une séance passée (terminee)', () => {
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
    const fixture = TestBed.createComponent(InscritsComponent);
    fixture.componentRef.setInput('id', '1');
    const composant = fixture.componentInstance;
    composant.donnees.set({
      inscrits: [
        reservation(1, 'terminee'),
        reservation(2, 'annulee'),
        reservation(3, 'confirmee'),
      ],
    } as Inscrits);
    composant.confirmesSeulement.set(true);
    expect(composant.lignes().map((r) => r.id)).toEqual([1, 3]);
  });
});
