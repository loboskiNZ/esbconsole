
import Alpine from 'alpinejs';
import { initPlaylistSortable } from './playlist-sortable';
import './virtual-console';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', initPlaylistSortable);
