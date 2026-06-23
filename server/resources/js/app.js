import Alpine from 'alpinejs';
import { portalLanding } from './portal';

window.Alpine = Alpine;
Alpine.data('portalLanding', portalLanding);
Alpine.start();
