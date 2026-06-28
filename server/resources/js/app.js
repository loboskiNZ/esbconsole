import Alpine from 'alpinejs';
import { portalLanding } from './portal';
import { portalOnboarding } from './onboarding';
import { profileEditor } from './profile-editor';
import { studioBandInvites } from './studio-band-invites';
import { studioChartsLauncher } from './studio-home';
import { studioCalendar, studioSchedule } from './studio-schedule';

import { bootStudioPlaylistOrder, initStudioPlaylistOrder } from './studio-playlist-order';
import { studioPlaylistPicker } from './studio-playlist-picker';
import { bootStudioPlaylistRemove } from './studio-playlist-remove';

window.Alpine = Alpine;
Alpine.data('portalLanding', (restoreUsername = '', loginFailed = false) => portalLanding(restoreUsername, loginFailed));
Alpine.data('portalOnboarding', (token = '', backgroundImages = []) => portalOnboarding(token, backgroundImages));
Alpine.data('profileEditor', profileEditor);
Alpine.data('studioBandInvites', studioBandInvites);
Alpine.data('studioChartsLauncher', studioChartsLauncher);
Alpine.data('studioSchedule', studioSchedule);
Alpine.data('studioCalendar', studioCalendar);
Alpine.data('studioPlaylistPicker', studioPlaylistPicker);

function bootStudioPlaylistUi() {
    bootStudioPlaylistOrder();
    bootStudioPlaylistRemove();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootStudioPlaylistUi);
} else {
    bootStudioPlaylistUi();
}

Alpine.start();
