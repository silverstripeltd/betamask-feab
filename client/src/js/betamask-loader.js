/**
 * Runs in the site's page. Shows the admin tool bar document in an iframe that
 * sits above the page, without the page's CSS or scripts being able to reach it:
 *
 * - the iframe lives in a closed shadow root, so site selectors and
 *   querySelectorAll() never match it;
 * - the host element joins the browser's top layer (popover), so no z-index,
 *   transform or overflow on the site can cover or clip it;
 * - the host's own position rules are !important inside the shadow root, which
 *   outranks even !important rules from the site's stylesheets.
 *
 * The bar asks for its size and state over postMessage; this script resizes the
 * iframe, moves the page content down while the bar is expanded, and remembers
 * when the member collapses it.
 */
import {
  BarState,
  COLLAPSED_COOKIE,
  MessageType,
  STAGE_PARAM,
} from './betamask-protocol';

const HOST_TAG = 'betamask-admin-bar';
const SCRIPT_SELECTOR = 'script[data-betamask-bar]';
const OFFSET_SELECTOR = 'link[data-betamask-offset]';
const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;
const MEDIA_ALL = 'all';
const MEDIA_NONE = 'not all';

// Phones get the 50px bar; wider screens the 60px one (see _topbar.scss)
const PHONE_QUERY = '(max-width: 767.98px)';

/**
 * Size in px used until the bar reports its own; matches the bar's layout
 *
 * @param {string} state
 * @returns {{width: ?number, height: number}}
 */
function initialSize(state) {
  const phone = window.matchMedia(PHONE_QUERY).matches;
  if (state === BarState.COLLAPSED) {
    return { width: 50, height: phone ? 39 : 44 };
  }
  return { width: null, height: phone ? 50 : 60 };
}

// Lengths are px because rem would follow the site's own root font size.
const HOST_CSS = `
  :host {
    all: initial !important;
    display: block !important;
    position: fixed !important;
    inset: 0 0 auto 0 !important;
    width: 100% !important;
    height: var(--betamask-height, 60px) !important;
    max-width: none !important;
    max-height: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    overflow: visible !important;
    background: transparent !important;
    color-scheme: light !important;
    z-index: 2147483647 !important;
    box-shadow: 0 4px 2px rgb(0 0 0 / 0.1) !important;
  }
  @media ${PHONE_QUERY} {
    :host {
      box-shadow: 0 3px 4.5px rgb(0 0 0 / 0.05) !important;
    }
  }
  :host([data-state="menu"]) {
    box-shadow: none !important;
  }
  :host([data-state="collapsed"]) {
    inset: 0 auto auto 0 !important;
    width: var(--betamask-width, 50px) !important;
    box-shadow: none !important;
  }
  .frame {
    display: block;
    width: 100%;
    height: 100%;
    border: 0;
    background: transparent;
    color-scheme: light;
  }
`;

/**
 * @param {boolean} collapsed
 */
function rememberCollapsed(collapsed) {
  const maxAge = collapsed ? COOKIE_MAX_AGE : 0;
  document.cookie = `${COLLAPSED_COOKIE}=1; path=/; max-age=${maxAge}; SameSite=Lax`;
}

/**
 * Moves the page content down by the bar's height while the bar is expanded
 *
 * @param {boolean} collapsed
 */
function setPageOffset(collapsed) {
  const offset = document.querySelector(OFFSET_SELECTOR);
  if (offset) {
    offset.media = collapsed ? MEDIA_NONE : MEDIA_ALL;
  }
}

/**
 * @param {HTMLElement} host
 * @param {{state: string, width: ?number, height: number}} layout
 */
function applyLayout(host, layout) {
  host.setAttribute('data-state', layout.state);
  host.style.setProperty('--betamask-height', `${Math.ceil(layout.height)}px`);
  if (layout.width) {
    host.style.setProperty('--betamask-width', `${Math.ceil(layout.width)}px`);
  }
}

/**
 * Puts the host in the top layer, above everything the site can stack. Browsers
 * without popover support keep it fixed at the highest z-index instead.
 *
 * @param {HTMLElement} host
 */
function raise(host) {
  if (typeof host.showPopover !== 'function') {
    return;
  }
  host.setAttribute('popover', 'manual');
  try {
    host.showPopover();
  } catch (e) {
    // Already shown, or the element was removed by the site
  }
}

/**
 * @param {string} stage
 */
function showStage(stage) {
  const url = new URL(window.location.href);
  url.searchParams.set(STAGE_PARAM, stage);
  window.location.assign(url.href);
}

/**
 * @param {HTMLElement} host
 * @param {HTMLIFrameElement} frame
 */
function listen(host, frame) {
  let collapsed = host.getAttribute('data-state') === BarState.COLLAPSED;

  window.addEventListener('message', (e) => {
    if (e.source !== frame.contentWindow || e.origin !== window.location.origin) {
      return;
    }
    const message = e.data || {};

    if (message.type === MessageType.LAYOUT) {
      applyLayout(host, message);
      const isCollapsed = message.state === BarState.COLLAPSED;
      if (isCollapsed !== collapsed) {
        collapsed = isCollapsed;
        setPageOffset(collapsed);
        rememberCollapsed(collapsed);
      }
    }

    if (message.type === MessageType.STAGE) {
      showStage(message.stage);
    }
  });
}

function init() {
  const script = document.querySelector(SCRIPT_SELECTOR);
  if (!script || !document.body || document.querySelector(HOST_TAG)) {
    return;
  }

  const collapsed = script.getAttribute('data-betamask-collapsed') === '1';
  const state = collapsed ? BarState.COLLAPSED : BarState.EXPANDED;

  const host = document.createElement(HOST_TAG);
  const root = host.attachShadow({ mode: 'closed' });
  const sheet = new CSSStyleSheet();
  sheet.replaceSync(HOST_CSS);
  root.adoptedStyleSheets = [sheet];

  const frame = document.createElement('iframe');
  frame.className = 'frame';
  frame.title = 'Admin tool bar';
  frame.src = script.getAttribute('data-betamask-bar');
  root.append(frame);

  applyLayout(host, { state, ...initialSize(state) });
  listen(host, frame);
  document.body.append(host);
  raise(host);
}

init();
