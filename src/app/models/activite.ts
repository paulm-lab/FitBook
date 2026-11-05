export interface Activite {
  id: number;
  nom: string;
  description: string;
  duree: number;
  niveau: string;
  image?: string | null;
}
