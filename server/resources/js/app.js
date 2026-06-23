import Alpine from 'alpinejs';
import { portalLanding } from './portal';
import { portalOnboarding } from './onboarding';

window.Alpine = Alpine;
Alpine.data('portalLanding', portalLanding);
Alpine.data('portalOnboarding', (token = '') => portalOnboarding(token));
Alpine.start();
