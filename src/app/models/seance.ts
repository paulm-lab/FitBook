export interface Seance {
  id: number;
  titre: string;
  description: string;
  dateDebut: string;
  dateFin: string;
  placesDisponibles: number;
  prix: string;
  activite?: { id: number; nom: string } | string | null;
  coach?: { id: number; nom: string; prenom: string } | null;
  salle?: { id: number; nom: string; adresse?: string } | null;
}
