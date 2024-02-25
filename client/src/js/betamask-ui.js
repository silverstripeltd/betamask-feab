import { createApp } from 'vue';

import TopBar from './components/TopBar/TopBar.vue';

const TopBarApp = createApp({});
TopBarApp.component('top-bar', TopBar);
TopBarApp.mount('#betamaskui');

export default TopBarApp;


