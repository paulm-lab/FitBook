import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { API_URL, params } from '../http';
import {
  Activite, Avis, Coach, CoachDetail, CriteresRecherche, Disponibilite, Note, Page, Salle, SalleDetail,
  SeanceDetail, SeanceResume, TypeCible,
} from '../models';

/** Catalogue public : recherche, fiches, activités, avis et disponibilités. */
@Injectable({ providedIn: 'root' })
export class CatalogueService {
  private readonly http = inject(HttpClient);

  rechercher<T = SeanceResume | Salle | Coach>(criteres: CriteresRecherche): Observable<Page<T>> {
    return this.http.get<Page<T>>(`${API_URL}/recherche`, { params: params(criteres) });
  }

  activites(): Observable<Activite[]> {
    return this.http.get<Activite[]>(`${API_URL}/activites`);
  }

  salles(): Observable<Salle[]> {
    return this.http.get<Salle[]>(`${API_URL}/salles`);
  }

  salle(id: number): Observable<SalleDetail> {
    return this.http.get<SalleDetail>(`${API_URL}/salles/${id}`);
  }

  coachs(): Observable<Coach[]> {
    return this.http.get<Coach[]>(`${API_URL}/coaches`);
  }

  coach(id: number): Observable<CoachDetail> {
    return this.http.get<CoachDetail>(`${API_URL}/coaches/${id}`);
  }

  seance(id: number): Observable<SeanceDetail> {
    return this.http.get<SeanceDetail>(`${API_URL}/seances/${id}`);
  }

  avis(type: TypeCible, cibleId: number, page = 1): Observable<Page<Avis> & { note: Note }> {
    return this.http.get<Page<Avis> & { note: Note }>(`${API_URL}/avis`, { params: params({ type, cibleId, page }) });
  }

  disponibilites(proprietaire: { coachId?: number; salleId?: number }): Observable<Disponibilite[]> {
    return this.http.get<Disponibilite[]>(`${API_URL}/disponibilites`, { params: params(proprietaire) });
  }
}
