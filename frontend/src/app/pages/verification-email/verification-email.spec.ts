import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { LangueService } from '../../core/i18n/langue.service';
import { VerificationEmailComponent } from './verification-email';

describe('VerificationEmailComponent', () => {
  const auth = { renvoyerVerification: vi.fn() };

  beforeEach(async () => {
    auth.renvoyerVerification.mockReset();
    await TestBed.configureTestingModule({
      imports: [VerificationEmailComponent],
      providers: [provideRouter([]), { provide: AuthService, useValue: auth }],
    }).compileComponents();
  });

  /** ?status= arrive dans l'input() grâce à withComponentInputBinding. */
  async function creerComposant(status: string) {
    const fixture = TestBed.createComponent(VerificationEmailComponent);
    fixture.componentRef.setInput('status', status);
    await fixture.whenStable();
    return fixture;
  }

  it('affiche la confirmation et le bouton de connexion en cas de succès', async () => {
    const fixture = await creerComposant('success');
    const element: HTMLElement = fixture.nativeElement;

    expect(element.textContent).toContain(
      'Votre adresse e-mail est vérifiée. Votre compte FitBook est actif.',
    );
    expect(element.querySelector('a.form-action')?.getAttribute('href')).toBe('/connexion');
    expect(element.querySelector('form')).toBeNull();
  });

  it('affiche le formulaire de renvoi pour un lien invalide ou expiré', async () => {
    const fixture = await creerComposant('error');
    const element: HTMLElement = fixture.nativeElement;

    expect(element.textContent).toContain(
      'Le lien de vérification est invalide ou expiré. Demandez un nouveau lien.',
    );
    expect(element.querySelector('form')).not.toBeNull();
  });

  it('renvoie le lien à l’adresse fournie et termine l’état d’envoi', async () => {
    const fixture = await creerComposant('error');
    auth.renvoyerVerification.mockReturnValue(of({ message: 'Demande prise en compte.' }));
    const component = fixture.componentInstance;
    component.formulaire.controls.email.setValue('camille@example.test');

    component.renvoyerLien();

    expect(auth.renvoyerVerification).toHaveBeenCalledWith('camille@example.test');
    expect(component.confirmation()).toBe('Demande prise en compte.');
    expect(component.envoi()).toBe(false);
  });

  it('n’appelle pas l’API pour une adresse invalide', async () => {
    const fixture = await creerComposant('error');
    fixture.componentInstance.formulaire.controls.email.setValue('invalide');

    fixture.componentInstance.renvoyerLien();

    expect(auth.renvoyerVerification).not.toHaveBeenCalled();
  });

  it('affiche le message du serveur si le renvoi échoue', async () => {
    const fixture = await creerComposant('error');
    auth.renvoyerVerification.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 429,
            error: { message: 'Trop de demandes. Réessayez dans quelques minutes.' },
          }),
      ),
    );
    const component = fixture.componentInstance;
    component.formulaire.controls.email.setValue('camille@example.test');

    component.renvoyerLien();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toBe('Trop de demandes. Réessayez dans quelques minutes.');
  });

  it('réactive le bouton si le service de renvoi lève une exception synchrone', async () => {
    const fixture = await creerComposant('error');
    auth.renvoyerVerification.mockImplementation(() => {
      throw new Error('service unavailable');
    });
    const component = fixture.componentInstance;
    component.formulaire.controls.email.setValue('camille@example.test');

    component.renvoyerLien();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toContain('La demande de renvoi');
  });

  it('s’affiche en anglais quand la langue choisie est l’anglais', async () => {
    const langue = TestBed.inject(LangueService);
    langue.choisir('en');
    try {
      const succes = await creerComposant('success');
      expect((succes.nativeElement as HTMLElement).querySelector('h1')?.textContent?.trim()).toBe(
        'Address verified',
      );

      const echec = await creerComposant('error');
      const element: HTMLElement = echec.nativeElement;
      expect(element.querySelector('h1')?.textContent?.trim()).toBe('Invalid verification link');
      expect(element.querySelector('button[type="submit"]')?.textContent?.trim()).toBe(
        'Resend the link',
      );
    } finally {
      // Langue mémorisée dans le navigateur : on revient au français pour les autres tests.
      langue.choisir('fr');
      TestBed.tick();
      localStorage.removeItem('fitbook-langue');
    }
  });
});
