import { registerLocaleData } from '@angular/common';
import { provideHttpClient } from '@angular/common/http';
import localeEnGb from '@angular/common/locales/en-GB';
import localeFr from '@angular/common/locales/fr';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AuthService } from '../core/auth/auth.service';
import { Favori } from '../core/models';
import { AvisSectionComponent } from './avis-section';
import { FavoriButtonComponent, FavorisStore } from './favori-button';
import { ConfirmService, EtoilesComponent, PaginationComponent } from './ui';

// Données de locale enregistrées par app.config.ts dans l'application (pipes nombreL, dateL).
registerLocaleData(localeFr);
registerLocaleData(localeEnGb);

/** Module de test avec HTTP simulé et un sportif connecté. */
function connecterSportif(): HttpTestingController {
  TestBed.configureTestingModule({
    providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
  });
  const http = TestBed.inject(HttpTestingController);
  TestBed.inject(AuthService).connexion('lea@exemple.test', 'secret123').subscribe();
  http.expectOne('/api/login').flush({
    id: 1,
    nom: 'Martin',
    prenom: 'Léa',
    email: 'lea@exemple.test',
    roles: ['ROLE_SPORTIF'],
    role: 'sportif',
  });
  return http;
}

describe('PaginationComponent', () => {
  function creer(page: number, pages: number) {
    const fixture = TestBed.createComponent(PaginationComponent);
    fixture.componentRef.setInput('page', page);
    fixture.componentRef.setInput('pages', pages);
    fixture.detectChanges();
    return fixture;
  }

  it('affiche au plus 5 numéros autour de la page courante', () => {
    const fixture = creer(7, 12);
    const numeros = [...fixture.nativeElement.querySelectorAll('.pagination-btn')].map(
      (b: HTMLElement) => b.textContent!.trim(),
    );
    expect(numeros).toEqual(['← Précédent', '5', '6', '7', '8', '9', 'Suivant →']);
    expect(fixture.nativeElement.querySelector('[aria-current=page]').textContent.trim()).toBe('7');
  });

  it("n'affiche rien pour une seule page et n'émet pas hors limites", () => {
    expect(creer(1, 1).nativeElement.querySelector('nav')).toBeNull();
    const fixture = creer(1, 3);
    const emis: number[] = [];
    fixture.componentInstance.changer.subscribe((p) => emis.push(p));
    fixture.componentInstance.aller(0);
    fixture.componentInstance.aller(1);
    fixture.componentInstance.aller(2);
    expect(emis).toEqual([2]);
  });
});

describe('EtoilesComponent', () => {
  it('arrondit la note et annonce une étiquette accessible', () => {
    const fixture = TestBed.createComponent(EtoilesComponent);
    fixture.componentRef.setInput('note', 3.6);
    fixture.componentRef.setInput('nbAvis', 12);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelectorAll('.on').length).toBe(4);
    expect(fixture.nativeElement.querySelector('.stars').getAttribute('aria-label')).toBe(
      'Note moyenne 3.6 sur 5',
    );
    expect(fixture.nativeElement.textContent).toContain('12 avis');
  });
});

describe('ConfirmService', () => {
  it('résout la promesse avec la réponse et referme la fenêtre', async () => {
    const service = TestBed.inject(ConfirmService);
    const reponse = service.demander({ titre: 'Annuler ?', message: 'Sûr ?' });
    expect(service.demande()?.titre).toBe('Annuler ?');
    service.demande()!.repondre(true);
    expect(await reponse).toBe(true);
    expect(service.demande()).toBeNull();
  });
});

describe('FavoriButtonComponent', () => {
  it('met à jour les favoris dès la réponse : le second clic retire le favori créé', () => {
    const http = connecterSportif();
    const fixture = TestBed.createComponent(FavoriButtonComponent);
    fixture.componentRef.setInput('type', 'salle');
    fixture.componentRef.setInput('cibleId', 3);
    fixture.detectChanges();
    TestBed.tick();
    http.match('/api/favoris').forEach((r) => r.flush([]));

    const bouton: HTMLButtonElement = fixture.nativeElement.querySelector('button');
    bouton.click();
    http
      .expectOne({ method: 'POST', url: '/api/favoris' })
      .flush({ id: 9, type: 'salle', date: '2026-11-06', cible: { id: 3 } } as Favori);
    // L'état vient de la réponse, pas d'un rechargement de la liste.
    http.expectNone({ method: 'GET', url: '/api/favoris' });
    fixture.detectChanges();
    expect(bouton.getAttribute('aria-pressed')).toBe('true');

    bouton.click();
    http.expectOne({ method: 'DELETE', url: '/api/favoris/9' }).flush(null);
    expect(TestBed.inject(FavorisStore).favoris()).toEqual([]);
  });
});

describe('AvisSectionComponent', () => {
  it("ne propose le formulaire d'une séance qu'une fois celle-ci terminée", () => {
    const http = connecterSportif();
    const fixture = TestBed.createComponent(AvisSectionComponent);
    fixture.componentRef.setInput('type', 'seance');
    fixture.componentRef.setInput('cibleId', 5);
    fixture.componentRef.setInput('terminee', false);
    fixture.detectChanges();
    TestBed.tick();
    http.match('/api/me/avis').forEach((r) => r.flush([]));
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('form')).toBeNull();
    expect(fixture.nativeElement.textContent).toContain('une fois la séance terminée');

    fixture.componentRef.setInput('terminee', true);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('form')).not.toBeNull();
  });
});
