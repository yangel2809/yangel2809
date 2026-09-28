import Alpine from 'alpinejs';
import dayBoard from './day-board';

Alpine.data('dayBoard', dayBoard);

window.Alpine = Alpine;
Alpine.start();
