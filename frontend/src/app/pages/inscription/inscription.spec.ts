import { HttpErrorResponse } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Subject, TimeoutError, of, throwError } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { InscriptionComponent } from './inscription';

describe('InscriptionComponent', () => {
  const auth = { inscription: vi.fn(), renvoyerVerification: vi.fn() };

  beforeEach(async () => {
    auth.inscription.mockReset();
    auth.renvoyerVerification.mockReset();
    await TestBed.configureTestingModule({
      imports: [InscriptionComponent],
      providers: [provideRouter([]), { provide: AuthService, useValue: auth }]
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
      password: 'MotDePasse-Local-123!'
    });
  }

  it('confirme le succès et indique que le lien de vérification a été mis en file', () => {
    auth.inscription.mockReturnValue(of({
      message: 'Compte créé.',
      email: 'camille@example.test',
      verificationEmailQueued: true
    }));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(auth.inscription).toHaveBeenCalledTimes(1);
    expect(component.confirmation).toContain('Compte créé.');
    expect(component.lienEnFile).toBe(true);
    expect(component.envoi).toBe(false);
  });

  it('confirme la création même si le backend ne peut pas mettre le courriel en file', () => {
    auth.inscription.mockReturnValue(of({
      message: 'Compte créé, courriel non mis en file.',
      email: 'camille@example.test',
      verificationEmailQueued: false
    }));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.emailInscrit).toBe('camille@example.test');
    expect(component.lienEnFile).toBe(false);
    expect(component.confirmation).toContain('Compte créé.');
    expect(component.envoi).toBe(false);
  });

  function inscrireAvecStatut(statut: 'sent' | 'queued' | 'failed'): string {
    auth.inscription.mockReturnValue(of({
      message: 'Compte créé.',
      email: 'camille@example.test',
      verificationEmailQueued: statut !== 'failed',
      verificationEmailStatus: statut
    }));
    const fixture = TestBed.createComponent(InscriptionComponent);
    remplirFormulaire(fixture.componentInstance);
    fixture.componentInstance.inscrire();
    fixture.detectChanges();
    return (fixture.nativeElement as HTMLElement).querySelector('[role="status"]')?.textContent ?? '';
  }

  it('annonce un e-mail envoyé seulement quand le serveur SMTP l’a accepté', () => {
    const texte = inscrireAvecStatut('sent');
    expect(texte).toContain('a été envoyé à');
    expect(texte).not.toContain("file d'attente");
  });

  it('indique un e-mail en file d’attente sans prétendre qu’il est parti', () => {
    const texte = inscrireAvecStatut('queued');
    expect(texte).toContain("en file d'attente");
    expect(texte).toContain("n'est pas encore parti");
    expect(texte).not.toContain('a été envoyé à');
  });

  it('signale l’échec d’envoi et propose un nouveau lien', () => {
    const texte = inscrireAvecStatut('failed');
    expect(texte).toContain("n'a pas pu être envoyé");
    expect(texte).toContain('Renvoyer le lien de vérification');
    expect(texte).not.toContain('a été envoyé à');
  });

  it('ne déduit jamais « envoyé » d’une réponse sans statut explicite', () => {
    auth.inscription.mockReturnValue(of({ message: 'Compte créé.', email: 'camille@example.test', verificationEmailQueued: true }));
    const component = creerComposant();
    remplirFormulaire(component);
    component.inscrire();
    expect(component.statutEmail).toBe('queued');
  });

  it('réactive le formulaire quand le service lève une exception synchrone', () => {
    auth.inscription.mockImplementation(() => { throw new Error('service unavailable'); });
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('Une erreur technique');
  });

  it('évite les appels pour un formulaire invalide', () => {
    const component = creerComposant();

    component.inscrire();

    expect(auth.inscription).not.toHaveBeenCalled();
    expect(component.envoi).toBe(false);
  });

  it('remet le bouton en état normal dès la réponse HTTP', () => {
    const response$ = new Subject<{ message: string; email: string; verificationEmailQueued: boolean }>();
    auth.inscription.mockReturnValue(response$);
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();
    expect(component.envoi).toBe(true);

    response$.next({ message: 'Compte créé.', email: 'camille@example.test', verificationEmailQueued: true });

    expect(component.envoi).toBe(false);
    expect(component.emailInscrit).toBe('camille@example.test');
  });

  it('réactive le formulaire et signale un e-mail déjà utilisé', () => {
    auth.inscription.mockReturnValue(throwError(() => new HttpErrorResponse({
      status: 409,
      error: { message: 'Adresse déjà utilisée.', errors: { email: 'Adresse déjà utilisée.' } }
    })));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi).toBe(false);
    expect(component.erreurChamp('email')).toBe('Cette adresse e-mail est déjà utilisée.');
  });

  it('réactive le formulaire et affiche un message si la requête expire', () => {
    auth.inscription.mockReturnValue(throwError(() => new TimeoutError()));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('trop de temps');
  });

  it('termine l’état d’envoi du lien après le succès du backend', () => {
    auth.renvoyerVerification.mockReturnValue(of({ message: 'Demande prise en compte.' }));
    const component = creerComposant();
    component.emailInscrit = 'camille@example.test';

    component.renvoyerLien();

    expect(auth.renvoyerVerification).toHaveBeenCalledWith('camille@example.test');
    expect(component.confirmation).toBe('Demande prise en compte.');
    expect(component.envoi).toBe(false);
  });

  it('termine l’état d’envoi et affiche une erreur si le renvoi échoue', () => {
    auth.renvoyerVerification.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 503 })));
    const component = creerComposant();
    component.emailInscrit = 'camille@example.test';

    component.renvoyerLien();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('Le lien de vérification');
  });

  it('termine l’état d’envoi et affiche une erreur si le renvoi expire', () => {
    auth.renvoyerVerification.mockReturnValue(throwError(() => new TimeoutError()));
    const component = creerComposant();
    component.emailInscrit = 'camille@example.test';

    component.renvoyerLien();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('trop de temps');
  });

  it('affiche « Création... » pendant l’envoi puis « Créer mon compte » après une erreur réseau', async () => {
    const response$ = new Subject<{ message: string; email: string; verificationEmailQueued: boolean }>();
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

  it('affiche la confirmation dans la page après la réponse du serveur', async () => {
    const response$ = new Subject<{ message: string; email: string; verificationEmailQueued: boolean }>();
    auth.inscription.mockReturnValue(response$);
    const fixture = TestBed.createComponent(InscriptionComponent);
    await fixture.whenStable();
    remplirFormulaire(fixture.componentInstance);
    const element: HTMLElement = fixture.nativeElement;

    element.querySelector('form')!.dispatchEvent(new Event('submit'));
    await fixture.whenStable();
    response$.next({ message: 'Compte créé.', email: 'camille@example.test', verificationEmailQueued: true });
    await fixture.whenStable();

    expect(element.querySelector('[role="status"]')?.textContent).toContain('camille@example.test');
    expect(element.querySelector('form')).toBeNull();
  });

  it('affiche les erreurs de champ renvoyées par le serveur', () => {
    auth.inscription.mockReturnValue(throwError(() => new HttpErrorResponse({
      status: 400,
      error: { message: 'Certains champs sont invalides.', errors: { telephone: 'Saisissez un numéro de téléphone valide (7 à 20 caractères).' } }
    })));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toBe('Certains champs sont invalides.');
    expect(component.erreurChamp('telephone')).toContain('numéro de téléphone');
  });

  it('réactive le formulaire et affiche un message en cas d’erreur serveur', () => {
    auth.inscription.mockReturnValue(throwError(() => new HttpErrorResponse({ status: 500 })));
    const component = creerComposant();
    remplirFormulaire(component);

    component.inscrire();

    expect(component.envoi).toBe(false);
    expect(component.erreur).toContain('Une erreur technique');
  });

  it('ne renvoie pas plusieurs demandes simultanément', () => {
    auth.renvoyerVerification.mockReturnValue(of({ message: 'Demande prise en compte.' }));
    const component = creerComposant();
    component.emailInscrit = 'camille@example.test';
    component.envoi = true;

    component.renvoyerLien();

    expect(auth.renvoyerVerification).not.toHaveBeenCalled();
  });
});
