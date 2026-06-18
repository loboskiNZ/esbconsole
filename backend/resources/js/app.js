
import Alpine from 'alpinejs';
import { initPlaylistSortable } from './playlist-sortable';
import './virtual-console';
import './x32-bus-eq';
import './x32-monitors-eq-panel';
import './x32-monitors-bus-master-control';
import './x32-monitors-group-control';
import './x32-monitors-send-control';
import './x32-monitors-responsive-panels';
import './x32-effects-parameter-cards';
import './x32-effects-slot-allocation';
import './x32-effects-routing-plan';
import './x32-effects-deploy';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', initPlaylistSortable);
