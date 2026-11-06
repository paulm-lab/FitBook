import { registerLocaleData } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import localeEnGb from '@angular/common/locales/en-GB';
import localeFr from '@angular/common/locales/fr';
import {
  ApplicationConfig,
  LOCALE_ID,
  inject,
  provideAppInitializer,
  provideBrowserGlobalErrorListeners,
} from '@angular/core';
import {
  TitleStrategy,
  provideRouter,
  withComponentInputBinding,
  withInMemoryScrolling,
} from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { routes } from './app.routes';
import { apiInterceptor } from './core/auth/api.interceptor';
import { AuthService } from './core/auth/auth.service';
import { TitreTraduitStrategy } from './core/i18n/titre.strategy';

// Dates, nombres et prix au format de la langue choisie (pipes dateL et nombreL).
registerLocaleData(localeFr);
registerLocaleData(localeEnGb);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(
      routes,
      // Les paramètres de route (:id) arrivent directement dans les input() des pages.
      withComponentInputBinding(),
      // Retour en haut géré dans AppComponent : seulement quand la page change (pas les filtres).
      withInMemoryScrolling({ scrollPositionRestoration: 'disabled', anchorScrolling: 'enabled' }),
    ),
    provideHttpClient(withInterceptors([apiInterceptor])),
    { provide: LOCALE_ID, useValue: 'fr' },
    { provide: TitleStrategy, useExisting: TitreTraduitStrategy },
    // Restaure la session (cookie JWT) avant le premier affichage : pas de clignotement du menu.
    provideAppInitializer(() => firstValueFrom(inject(AuthService).verifierSession())),
  ],
};
