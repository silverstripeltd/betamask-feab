/**
 * Runs inside the top bar's iframe. Keeps the parent wrapper in sync with the
 * framed page (address bar, document title, "Edit page" link) and keeps further
 * navigation inside the frame marked with the InlinePreview parameter.
 */
const PARAM = 'InlinePreview';
const STAGE_PARAM = 'stage';
const EVENT_NAME = 'betamaskTopBarLoaded';
const EDIT_LINK_SELECTOR = 'a[data-item="edit"]';
const EDIT_URL_META = 'meta[name="betamask-page-url"]';
const WRAPPER_SELECTOR = '#betamaskui-topbar';

/**
 * The wrapper document, when this page is framed by the top bar on the same origin
 *
 * @returns {Document|null}
 */
function getWrapperDocument() {
  if (window.parent === window) {
    return null;
  }

  try {
    const parentDocument = window.parent.document;
    return parentDocument.querySelector(WRAPPER_SELECTOR) ? parentDocument : null;
  } catch (e) {
    // A cross-origin parent is not the top bar
    return null;
  }
}

/**
 * Same-origin URL for a link target with the frame parameters added, or null when
 * the link leaves the site or is not a page navigation
 *
 * @param {string} href
 * @returns {URL|null}
 */
function toFrameUrl(href) {
  if (!href || href.startsWith('#')) {
    return null;
  }

  let url;
  try {
    url = new URL(href, document.baseURI);
  } catch (e) {
    return null;
  }

  if (url.origin !== window.location.origin) {
    return null;
  }

  const current = new URL(window.location.href);
  url.searchParams.set(PARAM, '1');
  if (!url.searchParams.has(STAGE_PARAM) && current.searchParams.has(STAGE_PARAM)) {
    url.searchParams.set(STAGE_PARAM, current.searchParams.get(STAGE_PARAM));
  }

  return url;
}

/**
 * Keeps an internal link inside the frame, and sends external links to the top
 * window, as most external sites refuse to be framed
 *
 * @param {HTMLAnchorElement} link
 */
function prepareLink(link) {
  const target = link.getAttribute('target');
  if (target && target !== '_self') {
    return;
  }

  const href = link.getAttribute('href');
  const url = toFrameUrl(href);

  if (url) {
    link.setAttribute('href', url.href);
    return;
  }

  if (href && /^https?:/i.test(href)) {
    link.setAttribute('target', '_top');
  }
}

/**
 * Keeps a same-site form submission marked as framed. GET forms replace the
 * query string on submit, so their frame parameters travel as hidden fields;
 * other methods keep them in the action URL.
 *
 * @param {HTMLFormElement} form
 */
function prepareForm(form) {
  const action = form.getAttribute('action') || window.location.href;
  const url = toFrameUrl(action);
  if (!url) {
    return;
  }

  if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
    form.setAttribute('action', url.href);
    return;
  }

  [PARAM, STAGE_PARAM].forEach((name) => {
    const value = url.searchParams.get(name);
    if (value === null || form.querySelector(`input[name="${name}"]`)) {
      return;
    }
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
  });
}

/**
 * @param {Document} wrapper
 * @param {object} detail
 */
function notifyWrapper(wrapper, detail) {
  wrapper.dispatchEvent(new CustomEvent(EVENT_NAME, { detail }));
}

function init() {
  const wrapper = getWrapperDocument();
  if (!wrapper) {
    return;
  }

  document.querySelectorAll('a[href]').forEach(prepareLink);

  const pageUrl = new URL(window.location.href);
  pageUrl.searchParams.delete(PARAM);

  const editUrl = document.querySelector(EDIT_URL_META)?.getAttribute('content');

  notifyWrapper(wrapper, {
    urls: editUrl ? { [EDIT_LINK_SELECTOR]: editUrl } : {},
    history: {
      title: document.title,
      url: pageUrl.href,
    },
  });

  // Capture phase, so links and forms added after load are prepared before the
  // browser acts on them. Nothing here cancels the event.
  document.addEventListener('click', (e) => {
    const link = e.target instanceof Element ? e.target.closest('a[href]') : null;
    if (link) {
      prepareLink(link);
    }
    notifyWrapper(wrapper, { close: 'all' });
  }, true);

  document.addEventListener('submit', (e) => {
    if (e.target instanceof HTMLFormElement) {
      prepareForm(e.target);
    }
  }, true);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
