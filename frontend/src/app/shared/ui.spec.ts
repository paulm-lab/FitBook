import { TestBed } from '@angular/core/testing';
import { ConfirmService, EtoilesComponent, PaginationComponent } from './ui';

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
    const numeros = [...fixture.nativeElement.querySelectorAll('.pagination-btn')].map((b: HTMLElement) => b.textContent!.trim());
    expect(numeros).toEqual(['← Précédent', '5', '6', '7', '8', '9', 'Suivant →']);
    expect(fixture.nativeElement.querySelector('[aria-current=page]').textContent.trim()).toBe('7');
  });

  it("n'affiche rien pour une seule page et n'émet pas hors limites", () => {
    expect(creer(1, 1).nativeElement.querySelector('nav')).toBeNull();
    const fixture = creer(1, 3);
    const emis: number[] = [];
    fixture.componentInstance.changer.subscribe(p => emis.push(p));
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
    expect(fixture.nativeElement.querySelector('.stars').getAttribute('aria-label')).toBe('Note moyenne 3.6 sur 5');
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
