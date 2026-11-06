import { TestBed } from '@angular/core/testing';
import { registerLocaleData } from '@angular/common';
import localeEnGb from '@angular/common/locales/en-GB';
import localeFr from '@angular/common/locales/fr';
import { EN } from './en/index';
import { LangueService } from './langue.service';
import { DateLanguePipe, NombreLanguePipe, TraductionPipe } from './pipes';

describe('LangueService', () => {
  beforeAll(() => {
    registerLocaleData(localeFr);
    registerLocaleData(localeEnGb);
  });

  beforeEach(() => {
    localStorage.clear();
    TestBed.resetTestingModule();
  });

  it('est en français par défaut et renvoie le texte source', () => {
    const langue = TestBed.inject(LangueService);
    expect(langue.langue()).toBe('fr');
    expect(langue.t('Bonjour {prenom}', { prenom: 'Camille' })).toBe('Bonjour Camille');
  });

  it('traduit en anglais et garde le français quand une traduction manque', () => {
    EN['Texte de test'] = 'Test text {n}';
    const langue = TestBed.inject(LangueService);
    langue.choisir('en');

    expect(langue.t('Texte de test', { n: 2 })).toBe('Test text 2');
    expect(langue.t('Texte jamais traduit')).toBe('Texte jamais traduit');
    delete EN['Texte de test'];
  });

  it('mémorise le choix et met à jour la langue du document', () => {
    TestBed.inject(LangueService).choisir('en');
    TestBed.tick();

    expect(document.documentElement.lang).toBe('en');
    expect(localStorage.getItem('fitbook-langue')).toBe('en');
  });

  it('formate dates et nombres selon la langue', () => {
    const langue = TestBed.inject(LangueService);
    const date = TestBed.runInInjectionContext(() => new DateLanguePipe());
    const nombre = TestBed.runInInjectionContext(() => new NombreLanguePipe());
    const t = TestBed.runInInjectionContext(() => new TraductionPipe());

    expect(date.transform('2026-11-10T10:00:00', 'EEEE d MMMM')).toBe('mardi 10 novembre');
    expect(nombre.transform(12.5, '1.2-2')).toBe('12,50');

    langue.choisir('en');
    expect(date.transform('2026-11-10T10:00:00', 'EEEE d MMMM')).toBe('Tuesday 10 November');
    expect(nombre.transform(12.5, '1.2-2')).toBe('12.50');
    expect(t.transform(null)).toBe('');
  });
});
