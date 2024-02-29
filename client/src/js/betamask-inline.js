
document.addEventListener('DOMContentLoaded', () => {
  /**
   * Modify URL string and include InlinePreview param
   *
   * @param urlString
   * @returns {string|null}
   */
  function modifyUrl(urlString)
  {
    if (urlString === '#') {
      return null;
    }

    try {
      let newUrl = urlString;

      if (urlString.indexOf('/') === 0) {
        newUrl = `${window.location.origin}${urlString}`;
      }

      const url = new URL(newUrl);
      url.searchParams.set('InlinePreview', '1');

      return url.href;
    } catch (e) {
      //
    }

    return null;
  }

  /**
   * Update href of an element with new URL from modifyUrl
   *
   * @param element
   */
  function updateHref(element)
  {
    const urlString = element.getAttribute('href');
    const newUrl = modifyUrl(urlString);
    if (!newUrl || element.getAttribute('target') === '_blank') {
      return;
    }
    element.setAttribute('href', newUrl);
    // eslint-disable-next-line no-param-reassign
    element.href = newUrl;
  }

  // Update all links within the page in the iframe to include InlinePreview=1 param
  // so that the view is always rendered as expected for iframe (without topbar)
  const links = document.querySelectorAll('a');
  links.forEach(element => {
    updateHref(element);
  });

  // Remove InlinePreview from current URL and update main window
  const url = new URL(window.location.href);
  url.searchParams.delete('InlinePreview');

  // Send a message to parent window about action to update in main window
  // urls: update URL of <css selector><url>
  window.parent.document.dispatchEvent(new CustomEvent('betamaskTopBarLoaded', {
    detail: {
      urls: {
        'a[data-item="topbar-1"]': document.querySelector('meta[name=\'betamask-page-url\']').getAttribute('content')
      },
      history: {
        title: document.title,
        url: url.href,
      }
    },
  }));

  // Any click in iframe
  document.addEventListener('click', (e) => {

    // ensure URL include InlinePreviews
    if (e.target && e.target?.href) {
      updateHref(e.target);
    }

    // Close all topbar open dropdown
    window.parent.document.dispatchEvent(new CustomEvent('betamaskTopBarLoaded', {
      detail: {
        close: 'all',
      },
    }));
  });
});

