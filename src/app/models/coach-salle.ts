export interface Coach {
  id: number;
  nom: string;
  prenom: string;
  specialite: string;
  description: string;
  photo?: string | null;
}

export interface Salle {
  id: number;
  nom: string;
  adresse: string;
  capacite: number;
  description: string;
  image?: string | null;
  equipements?: string | null;
}
