import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Seance } from '../models/seance';
import { API_URL } from '../api';

@Injectable({
  providedIn: 'root'
})
export class SeanceService {
  private readonly apiUrl = `${API_URL}/seances`;

  constructor(private http: HttpClient) {}

  getSeances(): Observable<Seance[]> {
    return this.http.get<Seance[]>(this.apiUrl);
  }

  getSeance(id: number): Observable<Seance> {
    return this.http.get<Seance>(`${this.apiUrl}/${id}`);
  }

  rechercherSeances(
    activite: string,
    lieu: string,
    date: string
  ): Observable<Seance[]> {
    let params = new HttpParams();

    if (activite) {
      params = params.set('activite', activite);
    }

    if (lieu) {
      params = params.set('lieu', lieu);
    }

    if (date) {
      params = params.set('date', date);
    }

    return this.http.get<Seance[]>(this.apiUrl, { params });
  }
}
