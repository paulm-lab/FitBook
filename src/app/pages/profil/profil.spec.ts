import { ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { of } from 'rxjs';
import { ProfilComponent } from './profil';
import { ProfilService } from '../../services/profil-service';

describe('ProfilComponent', () => {
  let fixture: ComponentFixture<ProfilComponent>;
  const profil = { id: 8, nom: 'Martin', prenom: 'Léa', email: 'lea@example.test', telephone: '+33123456789' };
  const profils = {
    getProfil: () => of(profil),
    modifierProfil: (value: typeof profil) => of({ ...profil, ...value })
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProfilComponent],
      providers: [provideRouter([]), { provide: ProfilService, useValue: profils }]
    }).compileComponents();
    fixture = TestBed.createComponent(ProfilComponent);
    fixture.detectChanges();
    await fixture.whenStable();
  });

  it('loads the current user profile and keeps email read only', () => {
    const component = fixture.componentInstance;
    expect(component.profil?.email).toBe('lea@example.test');
    expect(component.formulaire.controls.nom.value).toBe('Martin');
    const email = fixture.nativeElement.querySelector('#profile-email') as HTMLInputElement;
    expect(email.readOnly).toBe(true);
  });

  it('submits the editable profile fields', () => {
    const component = fixture.componentInstance;
    component.formulaire.setValue({ nom: 'Durand', prenom: 'Léa', telephone: '0612345678' });
    component.enregistrer();
    expect(component.profil?.nom).toBe('Durand');
    expect(component.confirmation).toBe('Votre profil a été mis à jour.');
  });
});
