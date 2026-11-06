import { Component, ElementRef, computed, input, signal, viewChild } from '@angular/core';
import { I18N } from '../../core/i18n/pipes';

type VisualPage =
  | 'home'
  | 'activities'
  | 'sessions'
  | 'coaches'
  | 'rooms'
  | 'pricing'
  | 'login'
  | 'signup'
  | 'booking';
interface VisualPhoto {
  src: string;
  alt: string;
  label: string;
}
interface VisualContent {
  kicker: string;
  title: string;
  description: string;
  hero: VisualPhoto;
  photos: VisualPhoto[];
  video: string;
  videoPoster: string;
  videoLabel: string;
}

const photo = (id: string, alt: string, label: string, width = 820) => ({
  src: `https://images.unsplash.com/${id}?auto=format&fit=crop&w=${width}&q=72`,
  alt,
  label,
});
const photoVideo = 'https://videos.pexels.com/video-files/3195394/3195394-hd_1920_1080_25fps.mp4';

const content: Record<VisualPage, VisualContent> = {
  home: {
    kicker: 'LE SPORT À VOTRE RYTHME',
    title: 'Trouvez votre prochain élan.',
    description: 'Des images pour vous inspirer, des séances pour passer à l’action.',
    hero: photo(
      'photo-1534438327276-14e5300c3a48',
      'Salle de sport lumineuse avec équipements',
      'Énergie en salle',
      1400,
    ),
    photos: [
      photo('photo-1544367567-0f2fcb009e0b', 'Personne en posture de yoga', 'Yoga'),
      photo('photo-1549719386-74dfcbf7dbed', 'Entraînement de boxe', 'Boxe'),
      photo('photo-1517836357463-d25dfeac3438', 'Entraînement de renforcement', 'Renforcement'),
      photo('photo-1552674605-46e432e1a7ee', 'Course à pied en plein air', 'Running'),
    ],
    video: photoVideo,
    videoPoster: photo(
      'photo-1517836357463-d25dfeac3438',
      'Entraînement sportif',
      'Sport en mouvement',
    ).src,
    videoLabel: 'Découvrez l’énergie des entraînements FitBook.',
  },
  activities: {
    kicker: 'ACTIVITÉS SPORTIVES',
    title: 'Une pratique qui vous ressemble.',
    description: 'Explorez les disciplines et trouvez l’ambiance qui vous motive.',
    hero: photo(
      'photo-1544367567-0f2fcb009e0b',
      'Cours collectif de yoga',
      'Choisir son activité',
      1400,
    ),
    photos: [
      photo('photo-1544367567-0f2fcb009e0b', 'Séance de yoga', 'Yoga'),
      photo('photo-1534438327276-14e5300c3a48', 'Espace de fitness', 'Fitness'),
      photo('photo-1549719386-74dfcbf7dbed', 'Sac de frappe pour la boxe', 'Boxe'),
      photo('photo-1517836357463-d25dfeac3438', 'Entraînement de musculation', 'Renforcement'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1544367567-0f2fcb009e0b', 'Démonstration de yoga', 'Yoga').src,
    videoLabel: 'Un aperçu pour découvrir une pratique.',
  },
  sessions: {
    kicker: 'SÉANCES',
    title: 'Passez de l’envie à l’action.',
    description: 'Des formats et des intensités pour tous les rythmes.',
    hero: photo(
      'photo-1517836357463-d25dfeac3438',
      'Atelier de renforcement accompagné',
      'Séance guidée',
      1400,
    ),
    photos: [
      photo('photo-1534438327276-14e5300c3a48', 'Cours de fitness en salle', 'Fitness'),
      photo('photo-1544367567-0f2fcb009e0b', 'Cours de yoga', 'Yoga'),
      photo('photo-1552674605-46e432e1a7ee', 'Groupe de course à pied', 'Running'),
      photo('photo-1549719386-74dfcbf7dbed', 'Entraînement de boxe', 'Boxe'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1534438327276-14e5300c3a48', 'Séance en salle', 'Entraînement').src,
    videoLabel: 'Voyez l’ambiance avant de réserver.',
  },
  coaches: {
    kicker: 'COACHS',
    title: 'Des pros pour vous accompagner.',
    description: 'Trouvez le bon encadrement pour progresser en confiance.',
    hero: photo(
      'photo-1567013127542-490d757e51fc',
      'Coach sportif en entraînement',
      'Coaching sportif',
      1400,
    ),
    photos: [
      photo('photo-1567013127542-490d757e51fc', 'Coach en salle de sport', 'Coaching'),
      photo('photo-1517836357463-d25dfeac3438', 'Séance guidée de renforcement', 'Renforcement'),
      photo('photo-1544367567-0f2fcb009e0b', 'Coach de yoga', 'Yoga'),
      photo('photo-1549719386-74dfcbf7dbed', 'Préparation de boxe', 'Boxe'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1567013127542-490d757e51fc', 'Coaching sportif', 'Coach').src,
    videoLabel: 'Découvrez les gestes et l’énergie du coaching.',
  },
  rooms: {
    kicker: 'SALLES',
    title: 'Des espaces pour bouger.',
    description: 'Des lieux équipés pour pratiquer dans de bonnes conditions.',
    hero: photo(
      'photo-1534438327276-14e5300c3a48',
      'Salle de sport avec équipements',
      'Espaces de pratique',
      1400,
    ),
    photos: [
      photo('photo-1534438327276-14e5300c3a48', 'Salle de fitness équipée', 'Fitness'),
      photo('photo-1571902943202-507ec2618e8f', 'Équipement sportif en salle', 'Équipements'),
      photo('photo-1544367567-0f2fcb009e0b', 'Espace calme pour le yoga', 'Yoga'),
      photo('photo-1517836357463-d25dfeac3438', 'Espace de renforcement', 'Training'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1534438327276-14e5300c3a48', 'Salle de sport', 'Salle').src,
    videoLabel: 'Un aperçu des espaces et de l’ambiance.',
  },
  pricing: {
    kicker: 'TARIFS',
    title: 'Choisissez votre prochaine séance.',
    description: 'Les prix sont affichés clairement avant la réservation.',
    hero: photo(
      'photo-1517836357463-d25dfeac3438',
      'Séance de sport en petit groupe',
      'Tarifs clairs',
      1400,
    ),
    photos: [
      photo('photo-1544367567-0f2fcb009e0b', 'Cours collectif de yoga', 'Yoga'),
      photo('photo-1534438327276-14e5300c3a48', 'Salle de fitness', 'Fitness'),
      photo('photo-1549719386-74dfcbf7dbed', 'Cours de boxe', 'Boxe'),
      photo('photo-1552674605-46e432e1a7ee', 'Groupe de running', 'Running'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1517836357463-d25dfeac3438', 'Séance sportive', 'Séances').src,
    videoLabel: 'Chaque activité a son rythme et son tarif.',
  },
  login: {
    kicker: 'FITBOOK',
    title: 'Votre espace sportif.',
    description: 'Retrouvez vos activités et vos séances.',
    hero: photo('photo-1544367567-0f2fcb009e0b', 'Pratique douce du yoga', 'Votre espace', 900),
    photos: [
      photo('photo-1544367567-0f2fcb009e0b', 'Yoga', 'Yoga'),
      photo('photo-1534438327276-14e5300c3a48', 'Fitness', 'Fitness'),
      photo('photo-1552674605-46e432e1a7ee', 'Course en plein air', 'Running'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1544367567-0f2fcb009e0b', 'Yoga', 'Yoga').src,
    videoLabel: 'Une pause active vous attend.',
  },
  signup: {
    kicker: 'BIENVENUE',
    title: 'Commencez à bouger.',
    description: 'Créez votre espace et trouvez votre prochaine activité.',
    hero: photo(
      'photo-1552674605-46e432e1a7ee',
      'Course sportive en plein air',
      'Nouveau départ',
      900,
    ),
    photos: [
      photo('photo-1544367567-0f2fcb009e0b', 'Yoga', 'Yoga'),
      photo('photo-1549719386-74dfcbf7dbed', 'Boxe', 'Boxe'),
      photo('photo-1534438327276-14e5300c3a48', 'Fitness en salle', 'Fitness'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1552674605-46e432e1a7ee', 'Running', 'Running').src,
    videoLabel: 'Choisissez votre première séance.',
  },
  booking: {
    kicker: 'RÉSERVATION',
    title: 'Votre prochaine séance vous attend.',
    description: 'Un bon entraînement commence avec le bon cadre.',
    hero: photo(
      'photo-1534438327276-14e5300c3a48',
      'Espace sportif équipé',
      'En route vers la séance',
      1100,
    ),
    photos: [
      photo('photo-1534438327276-14e5300c3a48', 'Salle de fitness', 'Salle'),
      photo('photo-1567013127542-490d757e51fc', 'Accompagnement sportif', 'Coach'),
      photo('photo-1544367567-0f2fcb009e0b', 'Cours de yoga', 'Activité'),
    ],
    video: photoVideo,
    videoPoster: photo('photo-1534438327276-14e5300c3a48', 'Séance en salle', 'Séance').src,
    videoLabel: 'Visualisez l’ambiance avant de confirmer.',
  },
};

@Component({
  selector: 'app-visual-showcase',
  standalone: true,
  imports: [...I18N],
  templateUrl: './visual-showcase.html',
  styleUrl: './visual-showcase.css',
})
export class VisualShowcaseComponent {
  readonly page = input<VisualPage>('home');
  readonly compact = input(false);
  readonly videoUnavailable = signal(false);
  private readonly galleryTrack = viewChild<ElementRef<HTMLDivElement>>('galleryTrack');

  /** Textes en français (clés de traduction), traduits dans le gabarit. */
  readonly visuals = computed<VisualContent>(() => content[this.page()]);

  scrollGallery(direction: -1 | 1): void {
    this.galleryTrack()?.nativeElement.scrollBy({ left: direction * 300, behavior: 'smooth' });
  }

  useFallback(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;
    if (image.dataset['fallbackApplied']) return;
    image.dataset['fallbackApplied'] = 'true';
    image.src = '/assets/fitbook-media-fallback.svg';
  }

  markVideoUnavailable(): void {
    this.videoUnavailable.set(true);
  }
}
