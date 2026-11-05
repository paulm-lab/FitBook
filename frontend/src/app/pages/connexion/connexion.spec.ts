import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, provideRouter } from '@angular/router';
import { Subject, of, throwError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { ConnexionComponent } from './connexion';

describe('ConnexionComponent', () => {
  const auth = { connexion: vi.fn(), renvoyerVerification: vi.fn(), espace: () => '/mon-espace' };
  let returnUrl: string | null = null;

  beforeEach(async () => {
    auth.connexion.mockReset();
    auth.renvoyerVerification.mockReset();
    returnUrl = null;
    await TestBed.configureTestingModule({
      imports: [ConnexionComponent],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: auth },
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { queryParamMap: { get: () => returnUrl } } },
        },
      ],
    }).compileComponents();
  });

  function creerComposant(): ConnexionComponent {
    const component = TestBed.createComponent(ConnexionComponent).componentInstance;
    component.formulaire.setValue({ email: 'camille@example.test', password: 'MotDePasse-123!' });
    return component;
  }

  it('connecte l’utilisateur et le redirige vers la page demandée', () => {
    returnUrl = '/profil';
    auth.connexion.mockReturnValue(
      of({
        id: 1,
        nom: 'Test',
        prenom: 'Camille',
        email: 'camille@example.test',
        telephone: '0612345678',
      }),
    );
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    const component = creerComposant();

    component.connecter();

    expect(auth.connexion).toHaveBeenCalledWith('camille@example.test', 'MotDePasse-123!');
    expect(navigate).toHaveBeenCalledWith('/profil');
    expect(component.envoi()).toBe(false);
  });

  it('refuse une redirection vers un autre site et ouvre l’espace du rôle', () => {
    returnUrl = '//exemple.com';
    auth.connexion.mockReturnValue(
      of({
        id: 1,
        nom: 'Test',
        prenom: 'Camille',
        email: 'camille@example.test',
        telephone: '0612345678',
      }),
    );
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);

    creerComposant().connecter();

    expect(navigate).toHaveBeenCalledWith('/mon-espace');
  });

  it('affiche un message pour des identifiants incorrects', () => {
    auth.connexion.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 401 })));
    const component = creerComposant();

    component.connecter();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toBe('Adresse e-mail ou mot de passe incorrect.');
  });

  it('affiche le message du serveur pour un compte non activé', () => {
    auth.connexion.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 403,
            error: { message: 'Confirmez votre adresse e-mail avant de vous connecter.' },
          }),
      ),
    );
    const component = creerComposant();

    component.connecter();

    expect(component.erreur()).toBe('Confirmez votre adresse e-mail avant de vous connecter.');
    expect(component.nonVerifie()).toBe(true);
  });

  it('renvoie le lien de confirmation pour un compte non vérifié', () => {
    auth.renvoyerVerification.mockReturnValue(of({ message: 'Demande prise en compte.' }));
    const component = creerComposant();

    component.renvoyerLien();

    expect(auth.renvoyerVerification).toHaveBeenCalledWith('camille@example.test');
    expect(component.renvoiMessage()).toBe('Demande prise en compte.');
    expect(component.renvoiEnCours()).toBe(false);
  });

  it('n’affiche pas le renvoi de lien pour un compte bloqué', () => {
    auth.connexion.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 403,
            error: { message: 'Ce compte a été bloqué. Contactez l’administrateur.' },
          }),
      ),
    );
    const component = creerComposant();

    component.connecter();

    expect(component.erreur()).toContain('bloqué');
    expect(component.nonVerifie()).toBe(false);
  });

  it('affiche le message de limitation après trop de tentatives', () => {
    auth.connexion.mockReturnValue(
      throwError(
        () => new HttpErrorResponse({ status: 429, error: { message: 'Trop de tentatives.' } }),
      ),
    );
    const component = creerComposant();

    component.connecter();

    expect(component.erreur()).toBe('Trop de tentatives.');
  });

  it('réactive le formulaire si le service lève une exception synchrone', () => {
    auth.connexion.mockImplementation(() => {
      throw new Error('service unavailable');
    });
    const component = creerComposant();

    component.connecter();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toBe('Connexion impossible. Réessayez plus tard.');
  });

  it('n’appelle pas l’API pour un formulaire invalide', () => {
    const component = TestBed.createComponent(ConnexionComponent).componentInstance;

    component.connecter();

    expect(auth.connexion).not.toHaveBeenCalled();
  });

  it('réaffiche « Se connecter » dans la page après une erreur réseau', async () => {
    const response$ = new Subject<never>();
    auth.connexion.mockReturnValue(response$);
    const fixture = TestBed.createComponent(ConnexionComponent);
    await fixture.whenStable();
    fixture.componentInstance.formulaire.setValue({
      email: 'camille@example.test',
      password: 'MotDePasse-123!',
    });
    const element: HTMLElement = fixture.nativeElement;
    const bouton = () => element.querySelector('button[type="submit"]') as HTMLButtonElement;

    element.querySelector('form')!.dispatchEvent(new Event('submit'));
    await fixture.whenStable();
    expect(bouton().textContent?.trim()).toBe('Connexion...');

    response$.error(new HttpErrorResponse({ status: 0 }));
    await fixture.whenStable();
    expect(bouton().textContent?.trim()).toBe('Se connecter');
    expect(bouton().disabled).toBe(false);
    expect(element.querySelector('[role="alert"]')?.textContent).toContain('injoignable');
  });
});
