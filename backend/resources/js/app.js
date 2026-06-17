
import Alpine from 'alpinejs';
import { initPlaylistSortable } from './playlist-sortable';
import './virtual-console';
import './x32-bus-eq';
import './x32-monitors-eq-panel';
import './x32-monitors-bus-master-control';
import './x32-monitors-group-control';
import './x32-monitors-send-control';
import './x32-monitors-responsive-panels';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', initPlaylistSortable);
