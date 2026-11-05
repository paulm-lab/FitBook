import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { AuthService } from './auth-service';
import { ProfilService } from './profil-service';
import { UtilisateurConnecte } from '../models/utilisateur';

describe('ProfilService', () => {
  let service: ProfilService;
  let http: HttpTestingController;
  let auth: AuthService;
  const profil = { id: 3, nom: 'Martin', prenom: 'Lea', email: 'lea@example.test', telephone: '0612345678' };

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    service = TestBed.inject(ProfilService);
    http = TestBed.inject(HttpTestingController);
    auth = TestBed.inject(AuthService);
  });

  afterEach(() => http.verify());

  it('loads the current profile with the authenticated session cookie', () => {
    let result: UtilisateurConnecte | undefined;
    service.getProfil().subscribe(value => result = value);

    const request = http.expectOne('/api/me/profile');
    expect(request.request.method).toBe('GET');
    expect(request.request.withCredentials).toBe(true);
    request.flush(profil);
    expect(result).toEqual(profil);
  });

  it('updates only the editable fields and refreshes the shared session user', () => {
    const update = { nom: 'Durand', prenom: 'Lea', telephone: '0712345678' };
    let result: UtilisateurConnecte | undefined;
    service.modifierProfil(update).subscribe(value => result = value);

    const request = http.expectOne('/api/me/profile');
    expect(request.request.method).toBe('PUT');
    expect(request.request.withCredentials).toBe(true);
    expect(request.request.body).toEqual(update);
    request.flush({ ...profil, ...update });
    expect(result?.nom).toBe('Durand');
    expect(auth.utilisateur?.telephone).toBe('0712345678');
  });
});
