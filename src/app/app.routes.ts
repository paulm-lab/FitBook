import { Routes } from '@angular/router';
import { AccueilComponent } from './pages/accueil/accueil';
import { Seances } from './pages/seances/seances';
import { ActivitesComponent } from './pages/activites/activites';
import { CoachesSallesComponent } from './pages/coaches-salles/coaches-salles';
import { TarifsComponent } from './pages/tarifs/tarifs';
import { ConnexionComponent } from './pages/connexion/connexion';
import { InscriptionComponent } from './pages/inscription/inscription';
import { ReservationComponent } from './pages/reservation/reservation';
import { VerificationEmailComponent } from './pages/verification-email/verification-email';
import { ProfilComponent } from './pages/profil/profil';

export const routes: Routes = [
  {
    path: '',
    component: AccueilComponent
  },
  {
    path: 'seances',
    component: Seances
  },
  { path: 'activites', component: ActivitesComponent },
  { path: 'coaches-salles', component: CoachesSallesComponent },
  { path: 'coaches', component: CoachesSallesComponent, data: { section: 'coaches' } },
  { path: 'salles', component: CoachesSallesComponent, data: { section: 'salles' } },
  { path: 'tarifs', component: TarifsComponent },
  { path: 'connexion', component: ConnexionComponent },
  { path: 'inscription', component: InscriptionComponent },
  { path: 'verification-email', component: VerificationEmailComponent },
  { path: 'profil', component: ProfilComponent },
  { path: 'reservation', component: ReservationComponent },
  { path: '**', redirectTo: '' }
];
