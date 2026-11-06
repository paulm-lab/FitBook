import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';
import { Subject, TimeoutError, of, throwError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { InscriptionComponent } from './inscription';

describe('InscriptionComponent', () => {
  const auth = { inscription: vi.fn(), connexion: vi.fn(), espace: () => '/mon-espace' };

  beforeEach(async () => {
    auth.inscription.mockReset();
    auth.connexion.mockReset();
    // Connexion automatique après l'inscription : réussie par défaut.
    auth.connexion.mockReturnValue(of({ id: 1, email: 'camille@example.test', role: 'sportif' }));
    await TestBed.configureTestingModule({
      imports: [InscriptionComponent],
      providers: [provideRouter([]), { provide: AuthService, useValue: auth }],
    }).compileComponents();
  });

  function creerComposant(): InscriptionComponent {
    return TestBed.createComponent(InscriptionComponent).componentInstance;
  }

  function remplirFormulaire(component: InscriptionComponent): void {
    component.formulaire.setValue({
      prenom: 'Camille',
      nom: 'Test',
      email: 'camille@example.test',
      telephone: '+33123456789',
      password: 'MotDePasse-Local-123!',
      role: 'sportif',
    });
  }

  it('connecte automatiquement le nouveau compte et ouvre son espace', () => {
    auth.inscription.mockReturnValue(
      of({
        message: 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
        email: 'camille@example.test',
      }),
    );
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigateByUrl').mockResolvedValue(true);
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(auth.inscription).toHaveBeenCalledTimes(1);
    expect(auth.connexion).toHaveBeenCalledWith('camille@example.test', 'MotDePasse-Local-123!');
    expect(navigate).toHaveBeenCalledWith('/mon-espace');
    expect(component.confirmation()).toBe('');
    expect(component.envoi()).toBe(false);
  });

  it('propose de se connecter si la connexion automatique échoue', () => {
    auth.inscription.mockReturnValue(
      of({
        message: 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
        email: 'camille@example.test',
      }),
    );
    auth.connexion.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 0 })));
    const fixture = TestBed.createComponent(InscriptionComponent);
    const component = fixture.componentInstance;
    remplirFormulaire(component);

    component.inscrire();
    fixture.detectChanges();

    expect(component.confirmation()).toBe(
      'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
    );
    const element = fixture.nativeElement as HTMLElement;
    const statut = element.querySelector('[role="status"]');
    expect(statut?.querySelector('a')?.getAttribute('href')).toBe(
      '/connexion?email=camille@example.test',
    );
    // Un seul lien « Se connecter » : celui du bas de page est masqué après l'inscription.
    expect(element.querySelectorAll('a[href^="/connexion"]').length).toBe(1);
    expect(element.querySelector('.form-switch')).toBeNull();
  });

  it('envoie le rôle choisi à l’API', () => {
    auth.inscription.mockReturnValue(
      of({
        message: 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
        email: 'camille@example.test',
      }),
    );
    const component = creerComposant();
    remplirFormulaire(component);
    component.formulaire.controls.role.setValue('coach');

    component.inscrire();

    expect(auth.inscription).toHaveBeenCalledWith(
      expect.objectContaining({ role: 'coach', email: 'camille@example.test' }),
    );
  });

  it('envoie le rôle sportif par défaut et ne propose jamais administrateur', () => {
    auth.inscription.mockReturnValue(
      of({
        message: 'Compte créé avec succès. Vous pouvez maintenant vous connecter.',
        email: 'camille@example.test',
      }),
    );
    const fixture = TestBed.createComponent(InscriptionComponent);
    fixture.detectChanges();
    const valeurs = Array.from(
      (fixture.nativeElement as HTMLElement).querySelectorAll<HTMLInputElement>(
        'input[type="radio"]',
      ),
    ).map((i) => i.value);
    expect(valeurs).toEqual(['sportif', 'coach', 'gestionnaire']);

    const component = fixture.componentInstance;
    component.formulaire.patchValue({
      prenom: 'Camille',
      nom: 'Test',
      email: 'camille@example.test',
      telephone: '+33123456789',
      password: 'MotDePasse-Local-123!',
    });
    component.inscrire();

    expect(auth.inscription).toHaveBeenCalledWith(expect.objectContaining({ role: 'sportif' }));
  });

  it('réactive le formulaire quand le service lève une exception synchrone', () => {
    auth.inscription.mockImplementation(() => {
      throw new Error('service unavailable');
    });
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toContain('Une erreur technique');
  });

  it('évite les appels pour un formulaire invalide', () => {
    const component = creerComposant();

    component.inscrire();

    expect(auth.inscription).not.toHaveBeenCalled();
    expect(component.envoi()).toBe(false);
  });

  it('remet le bouton en état normal dès la réponse HTTP', () => {
    const response$ = new Subject<{
      message: string;
      email: string;
    }>();
    auth.inscription.mockReturnValue(response$);
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();
    expect(component.envoi()).toBe(true);

    response$.next({
      message: 'Compte créé.',
      email: 'camille@example.test',
    });

    expect(component.envoi()).toBe(false);
    expect(component.emailInscrit()).toBe('camille@example.test');
  });

  it('réactive le formulaire et signale un e-mail déjà utilisé', () => {
    auth.inscription.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 409,
            error: {
              message: 'Adresse déjà utilisée.',
              errors: { email: 'Adresse déjà utilisée.' },
            },
          }),
      ),
    );
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi()).toBe(false);
    expect(component.erreurChamp('email')).toBe('Cette adresse e-mail est déjà utilisée.');
  });

  it('réactive le formulaire et affiche un message si la requête expire', () => {
    auth.inscription.mockReturnValue(throwError(() => new TimeoutError()));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toContain('trop de temps');
  });

  it('affiche « Création... » pendant l’envoi puis « Créer mon compte » après une erreur réseau', async () => {
    const response$ = new Subject<{
      message: string;
      email: string;
    }>();
    auth.inscription.mockReturnValue(response$);
    const fixture = TestBed.createComponent(InscriptionComponent);
    await fixture.whenStable();
    remplirFormulaire(fixture.componentInstance);
    const element: HTMLElement = fixture.nativeElement;
    const bouton = () => element.querySelector('button[type="submit"]') as HTMLButtonElement;
    expect(bouton().textContent?.trim()).toBe('Créer mon compte');

    element.querySelector('form')!.dispatchEvent(new Event('submit'));
    await fixture.whenStable();
    expect(bouton().textContent?.trim()).toBe('Création...');
    expect(bouton().disabled).toBe(true);

    response$.error(new HttpErrorResponse({ status: 0 }));
    await fixture.whenStable();
    expect(bouton().textContent?.trim()).toBe('Créer mon compte');
    expect(bouton().disabled).toBe(false);
    expect(element.querySelector('[role="alert"]')?.textContent).toContain('injoignable');
  });

  it('affiche la confirmation dans la page si la connexion automatique échoue', async () => {
    auth.connexion.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 401 })));
    const response$ = new Subject<{
      message: string;
      email: string;
    }>();
    auth.inscription.mockReturnValue(response$);
    const fixture = TestBed.createComponent(InscriptionComponent);
    await fixture.whenStable();
    remplirFormulaire(fixture.componentInstance);
    const element: HTMLElement = fixture.nativeElement;

    element.querySelector('form')!.dispatchEvent(new Event('submit'));
    await fixture.whenStable();
    response$.next({
      message: 'Compte créé.',
      email: 'camille@example.test',
    });
    await fixture.whenStable();

    expect(element.querySelector('[role="status"]')?.textContent).toContain('Se connecter');
    expect(element.querySelector('form')).toBeNull();
  });

  it('affiche les erreurs de champ renvoyées par le serveur', () => {
    auth.inscription.mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 400,
            error: {
              message: 'Certains champs sont invalides.',
              errors: { telephone: 'Saisissez un numéro de téléphone valide (7 à 20 caractères).' },
            },
          }),
      ),
    );
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toBe('Certains champs sont invalides.');
    expect(component.erreurChamp('telephone')).toContain('numéro de téléphone');
  });

  it('réactive le formulaire et affiche un message en cas d’erreur serveur', () => {
    auth.inscription.mockReturnValue(
      throwError(
        () => new HttpErrorResponse({ status: 500, error: { title: 'Internal Server Error' } }),
      ),
    );
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi()).toBe(false);
    expect(component.erreur()).toContain('Une erreur technique');
  });
});
