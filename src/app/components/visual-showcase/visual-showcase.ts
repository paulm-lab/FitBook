import { Component, ElementRef, Input, ViewChild } from '@angular/core';

type VisualPage = 'home' | 'activities' | 'sessions' | 'coaches' | 'rooms' | 'pricing' | 'login' | 'signup' | 'booking';
interface VisualPhoto { src: string; alt: string; label: string; }
interface VisualContent { kicker: string; title: string; description: string; hero: VisualPhoto; photos: VisualPhoto[]; video: string; videoPoster: string; videoLabel: string; }

const photo = (id: string, alt: string, label: string, width = 820) => ({ src: `https://images.unsplash.com/${id}?auto=format&fit=crop&w=${width}&q=72`, alt, label });
const photoVideo = 'https://videos.pexels.com/video-files/3195394/3195394-hd_1920_1080_25fps.mp4';

const content: Record<VisualPage, VisualContent> = {
  home: { kicker: 'LE SPORT A VOTRE RYTHME', title: 'Trouvez votre prochain elan.', description: 'Des images pour vous inspirer, des seances pour passer a l’action.', hero: photo('photo-1534438327276-14e5300c3a48', 'Salle de sport lumineuse avec equipements', 'Energie en salle', 1400), photos: [photo('photo-1544367567-0f2fcb009e0b', 'Personne en posture de yoga', 'Yoga'), photo('photo-1549719386-74dfcbf7dbed', 'Entrainement de boxe', 'Boxe'), photo('photo-1517836357463-d25dfeac3438', 'Entrainement de renforcement', 'Renforcement'), photo('photo-1552674605-46e432e1a7ee', 'Course a pied en plein air', 'Running')], video: photoVideo, videoPoster: photo('photo-1517836357463-d25dfeac3438', 'Entrainement sportif', 'Sport en mouvement').src, videoLabel: 'Decouvrez l’energie des entrainements FitBook.' },
  activities: { kicker: 'ACTIVITES SPORTIVES', title: 'Une pratique qui vous ressemble.', description: 'Explorez les disciplines et trouvez l’ambiance qui vous motive.', hero: photo('photo-1544367567-0f2fcb009e0b', 'Cours collectif de yoga', 'Choisir son activite', 1400), photos: [photo('photo-1544367567-0f2fcb009e0b', 'Seance de yoga', 'Yoga'), photo('photo-1534438327276-14e5300c3a48', 'Espace de fitness', 'Fitness'), photo('photo-1549719386-74dfcbf7dbed', 'Sac de frappe pour la boxe', 'Boxe'), photo('photo-1517836357463-d25dfeac3438', 'Entrainement de musculation', 'Renforcement')], video: photoVideo, videoPoster: photo('photo-1544367567-0f2fcb009e0b', 'Demonstration de yoga', 'Yoga').src, videoLabel: 'Un apercu pour decouvrir une pratique.' },
  sessions: { kicker: 'SEANCES', title: 'Passez de l’envie a l’action.', description: 'Des formats et des intensites pour tous les rythmes.', hero: photo('photo-1517836357463-d25dfeac3438', 'Atelier de renforcement accompagne', 'Seance guidee', 1400), photos: [photo('photo-1534438327276-14e5300c3a48', 'Cours de fitness en salle', 'Fitness'), photo('photo-1544367567-0f2fcb009e0b', 'Cours de yoga', 'Yoga'), photo('photo-1552674605-46e432e1a7ee', 'Groupe de course a pied', 'Running'), photo('photo-1549719386-74dfcbf7dbed', 'Entrainement de boxe', 'Boxe')], video: photoVideo, videoPoster: photo('photo-1534438327276-14e5300c3a48', 'Seance en salle', 'Entrainement').src, videoLabel: 'Voyez l’ambiance avant de reserver.' },
  coaches: { kicker: 'COACHS', title: 'Des pros pour vous accompagner.', description: 'Trouvez le bon encadrement pour progresser en confiance.', hero: photo('photo-1567013127542-490d757e51fc', 'Coach sportif en entrainement', 'Coaching sportif', 1400), photos: [photo('photo-1567013127542-490d757e51fc', 'Coach en salle de sport', 'Coaching'), photo('photo-1517836357463-d25dfeac3438', 'Seance guidee de renforcement', 'Renforcement'), photo('photo-1544367567-0f2fcb009e0b', 'Coach de yoga', 'Yoga'), photo('photo-1549719386-74dfcbf7dbed', 'Preparation de boxe', 'Boxe')], video: photoVideo, videoPoster: photo('photo-1567013127542-490d757e51fc', 'Coaching sportif', 'Coach').src, videoLabel: 'Decouvrez les gestes et l’energie du coaching.' },
  rooms: { kicker: 'SALLES', title: 'Des espaces pour bouger.', description: 'Des lieux equipes pour pratiquer dans de bonnes conditions.', hero: photo('photo-1534438327276-14e5300c3a48', 'Salle de sport avec equipements', 'Espaces de pratique', 1400), photos: [photo('photo-1534438327276-14e5300c3a48', 'Salle de fitness equipee', 'Fitness'), photo('photo-1571902943202-507ec2618e8f', 'Equipement sportif en salle', 'Equipements'), photo('photo-1544367567-0f2fcb009e0b', 'Espace calme pour le yoga', 'Yoga'), photo('photo-1517836357463-d25dfeac3438', 'Espace de renforcement', 'Training')], video: photoVideo, videoPoster: photo('photo-1534438327276-14e5300c3a48', 'Salle de sport', 'Salle').src, videoLabel: 'Un apercu des espaces et de l’ambiance.' },
  pricing: { kicker: 'TARIFS', title: 'Choisissez votre prochaine seance.', description: 'Les prix sont affiches clairement avant la reservation.', hero: photo('photo-1517836357463-d25dfeac3438', 'Seance de sport en petit groupe', 'Tarifs clairs', 1400), photos: [photo('photo-1544367567-0f2fcb009e0b', 'Cours collectif de yoga', 'Yoga'), photo('photo-1534438327276-14e5300c3a48', 'Salle de fitness', 'Fitness'), photo('photo-1549719386-74dfcbf7dbed', 'Cours de boxe', 'Boxe'), photo('photo-1552674605-46e432e1a7ee', 'Groupe de running', 'Running')], video: photoVideo, videoPoster: photo('photo-1517836357463-d25dfeac3438', 'Seance sportive', 'Seances').src, videoLabel: 'Chaque activite a son rythme et son tarif.' },
  login: { kicker: 'FITBOOK', title: 'Votre espace sportif.', description: 'Retrouvez vos activites et vos seances.', hero: photo('photo-1544367567-0f2fcb009e0b', 'Pratique douce du yoga', 'Votre espace', 900), photos: [photo('photo-1544367567-0f2fcb009e0b', 'Yoga', 'Yoga'), photo('photo-1534438327276-14e5300c3a48', 'Fitness', 'Fitness'), photo('photo-1552674605-46e432e1a7ee', 'Course en plein air', 'Running')], video: photoVideo, videoPoster: photo('photo-1544367567-0f2fcb009e0b', 'Yoga', 'Yoga').src, videoLabel: 'Une pause active vous attend.' },
  signup: { kicker: 'BIENVENUE', title: 'Commencez a bouger.', description: 'Creez votre espace et trouvez votre prochaine activite.', hero: photo('photo-1552674605-46e432e1a7ee', 'Course sportive en plein air', 'Nouveau depart', 900), photos: [photo('photo-1544367567-0f2fcb009e0b', 'Yoga', 'Yoga'), photo('photo-1549719386-74dfcbf7dbed', 'Boxe', 'Boxe'), photo('photo-1534438327276-14e5300c3a48', 'Fitness en salle', 'Fitness')], video: photoVideo, videoPoster: photo('photo-1552674605-46e432e1a7ee', 'Running', 'Running').src, videoLabel: 'Choisissez votre premiere seance.' },
  booking: { kicker: 'RESERVATION', title: 'Votre prochaine seance vous attend.', description: 'Un bon entrainement commence avec le bon cadre.', hero: photo('photo-1534438327276-14e5300c3a48', 'Espace sportif equipe', 'En route vers la seance', 1100), photos: [photo('photo-1534438327276-14e5300c3a48', 'Salle de fitness', 'Salle'), photo('photo-1567013127542-490d757e51fc', 'Accompagnement sportif', 'Coach'), photo('photo-1544367567-0f2fcb009e0b', 'Cours de yoga', 'Activite')], video: photoVideo, videoPoster: photo('photo-1534438327276-14e5300c3a48', 'Seance en salle', 'Seance').src, videoLabel: 'Visualisez l’ambiance avant de confirmer.' },
};

@Component({
  selector: 'app-visual-showcase',
  standalone: true,
  templateUrl: './visual-showcase.html',
  styleUrl: './visual-showcase.css',
})
export class VisualShowcaseComponent {
  @Input() page: VisualPage = 'home';
  @Input() compact = false;
  videoUnavailable = false;
  @ViewChild('galleryTrack') private galleryTrack?: ElementRef<HTMLDivElement>;

  get visuals(): VisualContent { return content[this.page]; }

  scrollGallery(direction: -1 | 1): void {
    this.galleryTrack?.nativeElement.scrollBy({ left: direction * 300, behavior: 'smooth' });
  }

  useFallback(event: Event): void {
    const image = event.currentTarget as HTMLImageElement;
    if (image.dataset['fallbackApplied']) return;
    image.dataset['fallbackApplied'] = 'true';
    image.src = '/assets/fitbook-media-fallback.svg';
  }

  markVideoUnavailable(): void { this.videoUnavailable = true; }
}
