import { EN_ADMIN } from './admin';
import { EN_COMMUN } from './commun';
import { EN_PAGES } from './pages';
import { EN_PRO } from './pro';
import { EN_SPORTIF } from './sportif';

/** Dictionnaire anglais complet : texte français → texte anglais. */
export const EN: Record<string, string> = {
  ...EN_COMMUN,
  ...EN_PAGES,
  ...EN_SPORTIF,
  ...EN_PRO,
  ...EN_ADMIN,
};
