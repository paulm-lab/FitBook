import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AppComponent } from './app';

describe('AppComponent', () => {
  beforeEach(() => {
    localStorage.clear();
    TestBed.configureTestingModule({
      imports: [AppComponent],
      providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()],
    });
  });

  afterEach(() => localStorage.clear());

  it('passe le site en anglais et indique la langue active', async () => {
    const fixture = TestBed.createComponent(AppComponent);
    await fixture.whenStable();
    const element: HTMLElement = fixture.nativeElement;
    const nav = () => element.querySelector('.main-nav')!.textContent!;
    const bouton = (langue: string) =>
      element.querySelector<HTMLButtonElement>(`.lang-switch button[lang="${langue}"]`)!;
    expect(nav()).toContain('Rechercher');
    expect(bouton('fr').getAttribute('aria-pressed')).toBe('true');

    bouton('en').click();
    await fixture.whenStable();

    expect(nav()).toContain('Search');
    expect(bouton('en').getAttribute('aria-pressed')).toBe('true');
    expect(bouton('fr').getAttribute('aria-pressed')).toBe('false');
    expect(document.documentElement.lang).toBe('en');

    bouton('fr').click();
    await fixture.whenStable();
    expect(nav()).toContain('Rechercher');
  });
});
