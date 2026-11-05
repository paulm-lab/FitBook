import { AfterViewInit, Component, ElementRef, NgZone, OnDestroy, effect, inject, input, output, viewChild } from '@angular/core';
import * as L from 'leaflet';

export interface PointCarte {
  id: number;
  latitude: number;
  longitude: number;
  titre: string;
  sousTitre?: string;
  lien: string;
}

/**
 * Carte Leaflet / OpenStreetMap (bonus US-V03) : un marqueur par salle, la position de
 * l'utilisateur et le rayon de recherche s'ils sont connus.
 *
 * Les textes des bulles sont insérés en texte (textContent), jamais en HTML : pas d'injection.
 */
@Component({
  selector: 'app-carte',
  template: `<div #carte class="map" role="region" aria-label="Carte des résultats"></div>`,
})
export class CarteComponent implements AfterViewInit, OnDestroy {
  readonly points = input<PointCarte[]>([]);
  readonly position = input<{ lat: number; lng: number } | null>(null);
  readonly rayonKm = input<number | null>(null);
  readonly hauteur = input('480px');
  readonly ouvrir = output<string>();

  private readonly element = viewChild.required<ElementRef<HTMLDivElement>>('carte');
  private readonly zone = inject(NgZone);
  private carte?: L.Map;
  private calque = L.layerGroup();

  // Icône dessinée en CSS (évite de dépendre des images du paquet Leaflet).
  private readonly icone = L.divIcon({ className: 'map-pin', html: '<span></span>', iconSize: [28, 28], iconAnchor: [14, 28], popupAnchor: [0, -26] });

  constructor() {
    effect(() => {
      this.points();
      this.position();
      this.rayonKm();
      this.dessiner();
    });
  }

  ngAfterViewInit(): void {
    this.element().nativeElement.style.height = this.hauteur();
    this.carte = L.map(this.element().nativeElement, { scrollWheelZoom: false }).setView([50.8466, 4.3528], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; contributeurs OpenStreetMap',
    }).addTo(this.carte);
    this.calque.addTo(this.carte);
    this.dessiner();
  }

  ngOnDestroy(): void {
    this.carte?.remove();
  }

  private dessiner(): void {
    if (!this.carte) return;
    this.calque.clearLayers();
    const limites: L.LatLngExpression[] = [];

    for (const point of this.points()) {
      const contenu = document.createElement('div');
      const titre = document.createElement('strong');
      titre.textContent = point.titre;
      contenu.append(titre);
      if (point.sousTitre) {
        const sous = document.createElement('div');
        sous.textContent = point.sousTitre;
        contenu.append(sous);
      }
      const lien = document.createElement('a');
      lien.href = point.lien;
      lien.textContent = 'Voir la fiche →';
      lien.addEventListener('click', event => {
        event.preventDefault();
        this.zone.run(() => this.ouvrir.emit(point.lien));
      });
      contenu.append(lien);

      L.marker([point.latitude, point.longitude], { icon: this.icone, title: point.titre, keyboard: true })
        .bindPopup(contenu)
        .addTo(this.calque);
      limites.push([point.latitude, point.longitude]);
    }

    const position = this.position();
    if (position) {
      L.circleMarker([position.lat, position.lng], { radius: 8, color: '#10213a', fillColor: '#3b82f6', fillOpacity: 1 })
        .bindTooltip('Vous êtes ici')
        .addTo(this.calque);
      limites.push([position.lat, position.lng]);
      if (this.rayonKm()) {
        L.circle([position.lat, position.lng], { radius: (this.rayonKm() ?? 0) * 1000, color: '#ff642d', weight: 1, fillOpacity: 0.06 }).addTo(this.calque);
      }
    }

    if (limites.length > 0) {
      this.carte.fitBounds(L.latLngBounds(limites), { padding: [40, 40], maxZoom: 14 });
    }
  }
}
