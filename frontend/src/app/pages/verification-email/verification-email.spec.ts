import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import { AuthService } from '../../services/auth-service';
import { VerificationEmailComponent } from './verification-email';

describe('VerificationEmailComponent', () => {
  const auth = { renvoyerVerification: vi.fn() };

  async function creerComposant(status: string) {
    auth.renvoyerVerification.mockReset();
    await TestBed.configureTestingModule({
      imports: [VerificationEmailComponent],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: auth },
        { provide: ActivatedRoute, useValue: { snapshot: { queryParamMap: { get: (key: string) => key === 'status' ? status : null } } } }
      ]
    }).compileComponents();
    return TestBed.createComponent(VerificationEmailComponent);
  }

  it('affiche la confirmation et le bouton de connexion en cas de succès', async () => {
    const fixture = await creerComposant('success');
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Votre adresse e-mail est vérifiée. Votre compte FitBook est actif.');
    expect(fixture.nativeElement.querySelector('a[routerLink="/connexion"]')).not.toBeNull();
    expect(fixture.nativeElement.querySelector('form')).toBeNull();
  });

  it('affiche le formulaire de renvoi pour un lien invalide ou expiré', async () => {
    const fixture = await creerComposant('error');
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Le lien de vérification est invalide ou expiré. Demandez un nouveau lien.');
    expect(fixture.nativeElement.querySelector('form')).not.toBeNull();
  });

  it('renvoie le lien à l’adresse fournie et termine l’état d’envoi', async () => {
    const fixture = await creerComposant('error');
    auth.renvoyerVerification.mockReturnValue(of({ message: 'Demande prise en compte.' }));
    const component = fixture.componentInstance;
    component.formulaire.controls.email.setValue('camille@example.test');

    component.renvoyerLien();

    expect(auth.renvoyerVerification).toHaveBeenCalledWith('camille@example.test');
    expect(component.confirmation).toBe('Demande prise en compte.');
    expect(component.envoi).toBe(false);
  });

  it('réactive le bouton et affiche une erreur si le renvoi échoue', async () => {
    const fixture = await creerComposant('error');
    auth.renvoyerVerification.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 503 })));
    const component = fixture.componentInstance;
    component.formulaire.controls.email.setValue('camille@example.test');

    component.renvoyerLien();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('La demande de renvoi');
  });

  it('réactive le bouton si le service de renvoi lève une exception synchrone', async () => {
    const fixture = await creerComposant('error');
    auth.renvoyerVerification.mockImplementation(() => { throw new Error('service unavailable'); });
    const component = fixture.componentInstance;
    component.formulaire.controls.email.setValue('camille@example.test');

    component.renvoyerLien();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('La demande de renvoi');
  });
});
