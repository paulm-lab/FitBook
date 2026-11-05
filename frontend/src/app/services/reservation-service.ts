import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { API_URL } from '../api';

export interface ReservationCreee { id: number; statut: string; nombrePlaces: number; montantTotal: string; }
@Injectable({ providedIn: 'root' })
export class ReservationService {
  constructor(private readonly http: HttpClient) {}
  reserver(seanceId: number, nombrePlaces = 1): Observable<ReservationCreee> {
    return this.http.post<ReservationCreee>(`${API_URL}/reservations`, { seanceId, nombrePlaces }, { withCredentials: true });
  }
}
