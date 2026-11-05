import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Activite } from '../models/activite';
import { Coach, Salle } from '../models/coach-salle';
import { API_URL } from '../api';

@Injectable({ providedIn: 'root' })
export class CatalogueService {
  constructor(private readonly http: HttpClient) {}

  getActivites(): Observable<Activite[]> { return this.http.get<Activite[]>(`${API_URL}/activites`); }
  getCoaches(): Observable<Coach[]> { return this.http.get<Coach[]>(`${API_URL}/coaches`); }
  getSalles(): Observable<Salle[]> { return this.http.get<Salle[]>(`${API_URL}/salles`); }

}
