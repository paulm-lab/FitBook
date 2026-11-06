import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, provideRouter } from '@angular/router';
import { Subject, of, throwError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { LangueService } from '../../core/i18n/langue.service';
import { ConnexionComponent } from './connexion';

describe('ConnexionComponent', () => {
  const auth = { connexion: vi.fn(), renvoyerVerification: vi.fn(), espace: () => '/mon-espace' };
  let returnUrl: string | null = null;
  let emailParam: string | null = null;

  beforeEach(async () => {
    auth.connexion.mockReset();
    auth.renvoyerVerification.mockReset();
    returnUrl = null;
    emailParam = null;
    await TestBed.configureTestingModule({
      imports: [ConnexionComponent],
      providers: [
        provideRouter([]),
        { provide: AuthService, useValue: auth },
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              queryParamMap: {
                get: (cle: string) => (cle === 'email' ? emailParam : returnUrl),
              },
            },
          },
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

  it('pré-remplit l’adresse transmise par la page d’inscription', () => {
    emailParam = 'camille@example.test';
    const component = TestBed.createComponent(ConnexionComponent).componentInstance;

    expect(component.formulaire.controls.email.value).toBe('camille@example.test');
  });

  it('affiche le message du serveur pour un compte bloqué', () => {
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

  function refusNonVerifie(message: string, code?: string) {
    return throwError(
      () =>
        new HttpErrorResponse({
          status: 403,
          error: code ? { message, code } : { message },
        }),
    );
  }

  it('propose de renvoyer le lien pour une adresse non confirmée (code de l’API)', async () => {
    auth.connexion.mockReturnValue(
      refusNonVerifie(
        'Confirmez votre adresse e-mail avant de vous connecter.',
        'email_non_verifie',
      ),
    );
    const fixture = TestBed.createComponent(ConnexionComponent);
    await fixture.whenStable();
    fixture.componentInstance.formulaire.setValue({
      email: 'camille@example.test',
      password: 'MotDePasse-123!',
    });

    fixture.componentInstance.connecter();
    await fixture.whenStable();

    const alerte = (fixture.nativeElement as HTMLElement).querySelector('[role="alert"]');
    expect(alerte?.textContent).toContain('Confirmez votre adresse e-mail');
    expect(alerte?.querySelector('button')?.textContent?.trim()).toBe(
      'Renvoyer le lien de vérification',
    );
  });

  it('reconnaît une adresse non confirmée en anglais, même sans code', () => {
    auth.connexion.mockReturnValue(
      refusNonVerifie('Please confirm your email address before logging in.'),
    );
    const component = creerComposant();

    component.connecter();

    expect(component.nonVerifie()).toBe(true);
    expect(component.erreur()).toBe('Please confirm your email address before logging in.');
  });

  it('renvoie le lien à l’adresse saisie et affiche la réponse de l’API', () => {
    auth.connexion.mockReturnValue(
      refusNonVerifie(
        'Confirmez votre adresse e-mail avant de vous connecter.',
        'email_non_verifie',
      ),
    );
    auth.renvoyerVerification.mockReturnValue(of({ message: 'Demande prise en compte.' }));
    const component = creerComposant();
    component.connecter();

    component.renvoyerLien();

    expect(auth.renvoyerVerification).toHaveBeenCalledWith('camille@example.test');
    expect(component.renvoiMessage()).toBe('Demande prise en compte.');
    expect(component.renvoiEnCours()).toBe(false);
  });

  it('signale l’échec du renvoi et réactive le bouton', () => {
    auth.renvoyerVerification.mockReturnValue(
      throwError(() => new HttpErrorResponse({ status: 429, error: { message: 'Trop.' } })),
    );
    const component = creerComposant();

    component.renvoyerLien();

    expect(component.renvoiEnCours()).toBe(false);
    expect(component.renvoiMessage()).toBe(
      'Le lien n’a pas pu être renvoyé. Réessayez dans quelques minutes.',
    );
  });

  it('affiche le bouton de renvoi en anglais', async () => {
    const langue = TestBed.inject(LangueService);
    langue.choisir('en');
    try {
      auth.connexion.mockReturnValue(
        refusNonVerifie(
          'Please confirm your email address before logging in.',
          'email_non_verifie',
        ),
      );
      const fixture = TestBed.createComponent(ConnexionComponent);
      await fixture.whenStable();
      fixture.componentInstance.formulaire.setValue({
        email: 'camille@example.test',
        password: 'MotDePasse-123!',
      });

      fixture.componentInstance.connecter();
      await fixture.whenStable();

      const bouton = (fixture.nativeElement as HTMLElement).querySelector('[role="alert"] button');
      expect(bouton?.textContent?.trim()).toBe('Resend the verification link');
    } finally {
      langue.choisir('fr');
      TestBed.tick();
      localStorage.removeItem('fitbook-langue');
    }
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

  it('s’affiche en anglais quand la langue choisie est l’anglais', async () => {
    const langue = TestBed.inject(LangueService);
    langue.choisir('en');
    try {
      const fixture = TestBed.createComponent(ConnexionComponent);
      await fixture.whenStable();
      const element: HTMLElement = fixture.nativeElement;

      expect(element.querySelector('h1')?.textContent?.trim()).toBe('Log in');
      expect(element.querySelector('button[type="submit"]')?.textContent?.trim()).toBe('Log in');
      expect(element.querySelector('label[for="login-password"]')?.textContent?.trim()).toBe(
        'Password',
      );
      expect(element.querySelector('.forgot-link')?.textContent?.trim()).toBe(
        'Forgotten your password?',
      );
    } finally {
      // Langue mémorisée dans le navigateur : on revient au français pour les autres tests.
      langue.choisir('fr');
      TestBed.tick();
      localStorage.removeItem('fitbook-langue');
    }
  });
});
