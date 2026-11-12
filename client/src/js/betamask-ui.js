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

/**
 * Whether this wrapper was itself loaded inside another top bar's iframe, which
 * happens when a navigation inside the frame could not be recognised as framed
 *
 * @returns {boolean}
 */
function isInsideTopBar() {
  if (window.parent === window) {
    return false;
  }

  try {
    return Boolean(window.parent.document.getElementById(MOUNT_ID));
  } catch (e) {
    return false;
  }
}

const mountPoint = document.getElementById(MOUNT_ID);
const frame = document.querySelector('.betamaskui__frame');

if (isInsideTopBar() && frame) {
  // Show the framed page in place of a second bar
  window.location.replace(frame.src);
} else if (mountPoint) {
  createApp(TopBar, readConfig(mountPoint)).mount(mountPoint);
}
