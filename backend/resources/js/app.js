

import Alpine from 'alpinejs';
import { initPlaylistSortable } from './playlist-sortable';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', initPlaylistSortable);
