import { createApp } from 'vue';

import TopBar from './components/TopBar/TopBar.vue';

const MOUNT_ID = 'betamaskui-topbar';

/**
 * Reads the bar's properties from the JSON the server renders into data-config
 *
 * @param {HTMLElement} element
 * @returns {object}
 */
function readConfig(element) {
  try {
    return JSON.parse(element.dataset.config || '{}');
  } catch (e) {
    return {};
  }
}

const mountPoint = document.getElementById(MOUNT_ID);

if (mountPoint) {
  createApp(TopBar, readConfig(mountPoint)).mount(mountPoint);
}
