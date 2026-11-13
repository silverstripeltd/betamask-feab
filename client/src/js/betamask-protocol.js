/**
 * Messages exchanged between the loader in the site's page and the bar document
 * in its iframe. Both sides only accept messages from the same origin.
 */
export const MessageType = Object.freeze({
  // Bar → page: { state, width, height } the iframe should take
  LAYOUT: 'betamask:layout',
  // Bar → page: { stage } to reload the page in another reading stage
  STAGE: 'betamask:stage',
});

export const BarState = Object.freeze({
  EXPANDED: 'expanded',
  MENU: 'menu',
  COLLAPSED: 'collapsed',
});

export const COLLAPSED_COOKIE = 'betamask_bar_collapsed';

export const STAGE_PARAM = 'stage';
