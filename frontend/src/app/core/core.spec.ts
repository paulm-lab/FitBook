import { HttpErrorResponse, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import {
  ActivatedRouteSnapshot,
  Router,
  RouterStateSnapshot,
  UrlTree,
  provideRouter,
} from '@angular/router';
import { firstValueFrom, isObservable, Observable } from 'rxjs';
import { apiInterceptor } from './auth/api.interceptor';
import { AuthService } from './auth/auth.service';
import { authGuard, roleGuard } from './auth/guards';
import { erreursChamps, messageErreur, params } from './http';
import { Utilisateur } from './models';

const SPORTIF: Utilisateur = {
  id: 1,
  nom: 'Martin',
  prenom: 'Léa',
  email: 'lea@exemple.test',
  roles: ['ROLE_SPORTIF'],
  role: 'sportif',
};

function configurer(): { http: HttpTestingController; auth: AuthService; router: Router } {
  TestBed.configureTestingModule({
    providers: [
      provideRouter([]),
      provideHttpClient(withInterceptors([apiInterceptor])),
      provideHttpClientTesting(),
    ],
  });
  return {
    http: TestBed.inject(HttpTestingController),
    auth: TestBed.inject(AuthService),
    router: TestBed.inject(Router),
  };
}

/** Exécute un guard dans le contexte d'injection et attend sa décision. */
async function decision(
  guard: ReturnType<typeof roleGuard>,
  url = '/coach',
): Promise<boolean | UrlTree> {
  const resultat = TestBed.runInInjectionContext(() =>
    guard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot),
  );
  return isObservable(resultat)
    ? firstValueFrom(resultat as Observable<boolean | UrlTree>)
    : (resultat as boolean | UrlTree);
}

describe('helpers HTTP', () => {
  it('ignore les paramètres vides', () => {
    expect(params({ q: 'yoga', ville: '', page: 2, niveau: null, tri: undefined }).toString()).toBe(
      'q=yoga&page=2',
    );
  });

  it('extrait le premier message de validation, puis le message général', () => {
    const e422 = new HttpErrorResponse({
      status: 422,
      error: { message: 'Invalide', errors: { email: 'Adresse invalide.' } },
    });
    expect(messageErreur(e422)).toBe('Adresse invalide.');
    expect(erreursChamps(e422)).toEqual({ email: 'Adresse invalide.' });
    expect(
      messageErreur(new HttpErrorResponse({ status: 409, error: { message: 'Complet.' } })),
    ).toBe('Complet.');
    expect(messageErreur(new HttpErrorResponse({ status: 0 }))).toContain('injoignable');
    // API arrêtée derrière le proxy de développement : 500 sans corps.
    expect(messageErreur(new HttpErrorResponse({ status: 500, error: null }))).toContain(
      'injoignable',
    );
    // Vraie erreur Symfony : corps JSON, message générique.
    expect(
      messageErreur(
        new HttpErrorResponse({ status: 500, error: { title: 'Internal Server Error' } }),
      ),
    ).toBe('Une erreur est survenue. Réessayez.');
    expect(
      erreursChamps(new HttpErrorResponse({ status: 409, error: { errors: { a: 'b' } } })),
    ).toEqual({});
  });
});

describe('AuthService et intercepteur', () => {
  it('restaure la session depuis /api/me, sans erreur si le visiteur est anonyme', async () => {
    const { http, auth } = configurer();
    const reponse = firstValueFrom(auth.verifierSession());
    http
      .expectOne('/api/me')
      .flush({ message: 'Authentification requise.' }, { status: 401, statusText: 'Unauthorized' });
    expect(await reponse).toBeNull();
    expect(auth.pret()).toBe(true);
    expect(auth.estConnecte()).toBe(false);
  });

  it('garde le jeton en mémoire et le joint aux appels suivants', async () => {
    const { http, auth } = configurer();
    const connexion = firstValueFrom(auth.connexion('lea@exemple.test', 'secret123'));
    const login = http.expectOne('/api/login');
    expect(login.request.withCredentials).toBe(true);
    login.flush({ ...SPORTIF, token: 'jeton-jwt' });
    expect(await connexion).toEqual(SPORTIF);
    expect(auth.espace()).toBe('/mon-espace');

    TestBed.inject(HttpClient).get('/api/favoris').subscribe();
    expect(http.expectOne('/api/favoris').request.headers.get('Authorization')).toBe(
      'Bearer jeton-jwt',
    );
  });

  it('sur un 401, oublie la session et renvoie vers /connexion avec la page demandée', async () => {
    const { http, auth, router } = configurer();
    const navigation = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    auth.connexion('lea@exemple.test', 'secret123').subscribe();
    http.expectOne('/api/login').flush({ ...SPORTIF, token: 'expire' });

    TestBed.inject(HttpClient)
      .get('/api/reservations')
      .subscribe({ error: () => undefined });
    http.expectOne('/api/reservations').flush({}, { status: 401, statusText: 'Unauthorized' });

    expect(auth.estConnecte()).toBe(false);
    expect(navigation).toHaveBeenCalledWith(['/connexion'], {
      queryParams: { returnUrl: router.url },
    });
  });

  it('visiteur avec un cookie invalide : efface le cookie puis rejoue la lecture une fois', async () => {
    const { http } = configurer();
    const client = TestBed.inject(HttpClient);
    const reponse = firstValueFrom(client.get('/api/salles'));
    const echec = client.get('/api/coachs');
    let erreur: unknown = null;
    echec.subscribe({ error: (e: unknown) => (erreur = e) });

    http.expectOne('/api/salles').flush({}, { status: 401, statusText: 'Unauthorized' });
    http.expectOne('/api/coachs').flush({}, { status: 401, statusText: 'Unauthorized' });
    // Un seul /api/logout pour les deux requêtes refusées.
    http.expectOne('/api/logout').flush({});

    http.expectOne('/api/salles').flush([{ id: 1 }]);
    expect(await reponse).toEqual([{ id: 1 }]);
    // Rejouée une seule fois : un second 401 remonte à l'appelant.
    http.expectOne('/api/coachs').flush({}, { status: 401, statusText: 'Unauthorized' });
    expect((erreur as HttpErrorResponse).status).toBe(401);
    http.verify();
  });

  it("l'administrateur hérite de tous les rôles", () => {
    const { http, auth } = configurer();
    auth.connexion('admin@exemple.test', 'secret123').subscribe();
    http.expectOne('/api/login').flush({ ...SPORTIF, role: 'admin', roles: ['ROLE_ADMIN'] });
    expect(auth.aLeRole('coach')).toBe(true);
    expect(auth.aLeRole('gestionnaire')).toBe(true);
  });
});

describe('guards', () => {
  it('renvoie un visiteur vers /connexion avec returnUrl', async () => {
    const { http, auth, router } = configurer();
    auth.verifierSession().subscribe();
    http.expectOne('/api/me').flush({}, { status: 401, statusText: 'Unauthorized' });

    const resultat = await decision(authGuard, '/mon-espace/favoris');
    expect(router.serializeUrl(resultat as UrlTree)).toBe(
      '/connexion?returnUrl=%2Fmon-espace%2Ffavoris',
    );
  });

  it("renvoie un sportif vers son espace s'il ouvre l'espace coach", async () => {
    const { http, auth, router } = configurer();
    auth.verifierSession().subscribe();
    http.expectOne('/api/me').flush(SPORTIF);

    expect(router.serializeUrl((await decision(roleGuard('coach'))) as UrlTree)).toBe(
      '/mon-espace',
    );
    expect(await decision(authGuard)).toBe(true);
  });

  it('attend la fin de /api/me avant de décider (rechargement de page)', async () => {
    const { http, auth } = configurer();
    auth.verifierSession().subscribe();
    const enAttente = decision(roleGuard('coach'));
    http.expectOne('/api/me').flush({ ...SPORTIF, role: 'coach', roles: ['ROLE_COACH'] });
    expect(await enAttente).toBe(true);
  });
});
