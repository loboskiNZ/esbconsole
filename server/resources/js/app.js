import Alpine from 'alpinejs';
import { portalLanding } from './portal';
import { portalOnboarding } from './onboarding';

window.Alpine = Alpine;
Alpine.data('portalLanding', portalLanding);
Alpine.data('portalOnboarding', (token = '', backgroundImages = []) => portalOnboarding(token, backgroundImages));
Alpine.start();
