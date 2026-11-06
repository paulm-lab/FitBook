// Modèles de l'API FitBook (docs/api.md) : clés en français, dates ISO 8601 avec décalage.

export type Role = 'sportif' | 'coach' | 'gestionnaire' | 'admin';
export type Niveau = 'debutant' | 'intermediaire' | 'avance';
export type StatutReservation = 'confirmee' | 'annulee' | 'terminee';
export type TypeCible = 'salle' | 'coach' | 'seance';

/** Liste paginée renvoyée par l'API. */
export interface Page<T> {
  items: T[];
  page: number;
  parPage: number;
  total: number;
  pages: number;
}

/** Erreur JSON de l'API : message général et, en 422, détail par champ. */
export interface ApiErreur {
  message?: string;
  errors?: Record<string, string>;
}

export interface Utilisateur {
  id: number;
  nom: string;
  prenom: string;
  email: string;
  telephone?: string | null;
  avatar?: string | null;
  roles: string[];
  role: Role;
  coachId?: number | null;
}

export interface Note {
  note: number | null;
  nbAvis: number;
}

export interface Activite {
  id: number;
  nom: string;
  description?: string | null;
  categorie: string;
  duree?: number | null;
  niveau?: string;
  image?: string | null;
}

export interface SeanceResume {
  id: number;
  titre: string;
  description?: string | null;
  dateDebut: string;
  dateFin: string;
  dateSeance: string;
  heureDebut: string;
  heureFin: string;
  capaciteMax: number;
  placesDisponibles: number;
  prix: string;
  niveau: Niveau;
  statut: 'active' | 'annulee';
  image?: string | null;
  activite: { id: number; nom: string; categorie?: string } | null;
  coach: { id: number; nom: string; prenom: string; photo?: string | null } | null;
  salle: {
    id: number;
    nom: string;
    adresse: string;
    ville?: string;
    latitude?: string | null;
    longitude?: string | null;
  } | null;
  // Présents dans les résultats de recherche.
  noteMoyenne?: number | null;
  nbAvis?: number;
  distanceKm?: number | null;
}

export interface Avis {
  id: number;
  note: number;
  commentaire: string | null;
  date: string;
  auteur: string | null;
  auteurId: number | null;
  estModere: boolean;
  estVisible: boolean;
  cible?: Cible | null;
}

export interface Cible {
  type: TypeCible;
  id: number;
  nom: string | null;
  image?: string | null;
  dateDebut?: string;
}

export interface Disponibilite {
  id: number;
  coachId: number | null;
  salleId: number | null;
  recurrent: boolean;
  jourSemaine: number | null;
  date: string | null;
  heureDebut: string;
  heureFin: string;
}

export interface SeanceDetail extends SeanceResume {
  noteOrganisateur: Note;
  note: Note;
  avis: Avis[];
  peutModifier: boolean;
  /** Vrai si l'utilisateur connecté a une réservation confirmée sur cette séance. */
  dejaReservee?: boolean;
}

export interface Salle {
  id: number;
  nom: string;
  adresse: string;
  ville: string;
  codePostal: string;
  latitude: string | null;
  longitude: string | null;
  telephone: string | null;
  email: string | null;
  horaires: Record<string, string> | null;
  capacite: number | null;
  description: string | null;
  image: string | null;
  photos: string[];
  noteMoyenne?: number | null;
  nbAvis?: number;
  distanceKm?: number | null;
}

export interface SalleDetail extends Salle {
  note: Note;
  avis: Avis[];
  prochainesSeances: SeanceResume[];
  disponibilites: Disponibilite[];
  gestionnaireId: number | null;
  peutModifier: boolean;
}

export interface Coach {
  id: number;
  nom: string;
  prenom: string;
  specialite: string;
  specialites: string[];
  description: string | null;
  biographie: string | null;
  experience: string | null;
  experienceAnnees: number | null;
  certification: string | null;
  tarifHoraire: string | null;
  ville: string | null;
  photo: string | null;
  noteMoyenne?: number | null;
  nbAvis?: number;
}

export interface CoachDetail extends Coach {
  note: Note;
  avis: Avis[];
  prochainesSeances: SeanceResume[];
  disponibilites: Disponibilite[];
  userId: number | null;
}

export interface Reservation {
  id: number;
  statut: StatutReservation;
  dateReservation: string;
  dateAnnulation: string | null;
  annulable: boolean;
  qrToken: string | null;
  seance: SeanceResume | null;
  sportif?: {
    id: number;
    nom: string;
    prenom: string;
    email: string;
    telephone: string | null;
  } | null;
}

export interface Favori {
  id: number;
  type: TypeCible;
  date: string;
  cible: Cible | null;
}

export interface NotificationFitBook {
  id: number;
  type: string;
  message: string;
  lue: boolean;
  date: string;
}

export interface Evolution {
  semaine: string;
  reservations: number;
}

export interface StatsSalle {
  nbSeances: number;
  capaciteTotale: number;
  nbReservations: number;
  tauxRemplissage: number | null;
  noteMoyenne: number | null;
  nbAvis: number;
}

export interface UtilisateurAdmin extends Utilisateur {
  estVerifie: boolean;
  estBloque: boolean;
  dateInscription: string;
}

/** Critères de /api/recherche, tels qu'ils apparaissent dans l'URL de /recherche. */
export interface CriteresRecherche {
  type?: 'seances' | 'salles' | 'coachs';
  q?: string;
  ville?: string;
  activite?: string;
  du?: string;
  au?: string;
  prixMax?: number | string;
  niveau?: Niveau | '';
  noteMin?: number | string;
  lat?: number;
  lng?: number;
  rayon?: number | string;
  tri?: 'date' | 'prix' | 'note' | 'distance' | 'nom';
  page?: number;
  parPage?: number;
}

export const NIVEAUX: Record<Niveau, string> = {
  debutant: 'Débutant',
  intermediaire: 'Intermédiaire',
  avance: 'Avancé',
};

export const ROLES: Record<Role, string> = {
  sportif: 'Sportif',
  coach: 'Coach',
  gestionnaire: 'Gestionnaire de salle',
  admin: 'Administrateur',
};

export const JOURS = ['', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
