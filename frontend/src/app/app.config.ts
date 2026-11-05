import { registerLocaleData } from '@angular/common';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import localeFr from '@angular/common/locales/fr';
import { ApplicationConfig, LOCALE_ID, inject, provideAppInitializer, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter, withComponentInputBinding, withInMemoryScrolling } from '@angular/router';
import { firstValueFrom } from 'rxjs';
import { routes } from './app.routes';
import { apiInterceptor } from './core/auth/api.interceptor';
import { AuthService } from './core/auth/auth.service';

// Dates, nombres et prix au format français (« lundi 10 novembre », « 12,50 € »).
registerLocaleData(localeFr);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(
      routes,
      // Les paramètres de route (:id) arrivent directement dans les input() des pages.
      withComponentInputBinding(),
      withInMemoryScrolling({ scrollPositionRestoration: 'top', anchorScrolling: 'enabled' }),
    ),
    provideHttpClient(withInterceptors([apiInterceptor])),
    { provide: LOCALE_ID, useValue: 'fr' },
    // Restaure la session (cookie JWT) avant le premier affichage : pas de clignotement du menu.
    provideAppInitializer(() => firstValueFrom(inject(AuthService).verifierSession())),
  ],
};
