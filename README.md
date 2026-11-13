# Betamask Admin Overlay

Adds an admin tool bar, styled with [betamask](https://github.com/silverstripeltd/betamask), to the
front end of a Silverstripe CMS site. Each CMS user turns it on for themselves.

![The tool bar on the front end](docs/images/BetaMask-front-end-bar.png)

## Requirements

* PHP ^8.3
* Silverstripe CMS 6.1+ (`silverstripe/admin` ^3.1, `silverstripe/siteconfig` ^6.1)
* [`silverstripeltd/featureflag`](https://github.com/silverstripeltd/featureflag)

## Installation

Both packages are private, so add their repositories to your project's `composer.json`. Composer does
not read `repositories` from a dependency:

```json
"repositories": [
    { "type": "vcs", "url": "git@github.com:silverstripeltd/betamask-feab.git" },
    { "type": "vcs", "url": "git@github.com:silverstripeltd/featureflag.git" }
]
```

```sh
composer require silverstripeltd/betamask-bao
```

## Turning the bar on

1. Go to `/admin/myprofile`
1. Tick **Admin tool bar** in the features section and save

The bar only appears for users who have ticked it.

## How it works

The page is rendered exactly as it is for any other visitor. For members with the bar turned on, two
tags are then added to the HTML, and nothing else in the page changes:

* a stylesheet in `<head>` that moves the page content down by the bar's height (66px) while the bar
  is expanded, so it starts below the bar instead of under it
* a small loader script before `</body>`

The loader shows the bar in an iframe that sits above the page:

* The bar is a separate document, so the site's CSS and scripts cannot change how it looks or behaves,
  and clicks on it never reach the site's own click handlers or analytics.
* The iframe lives in a closed shadow root, so site selectors and `querySelectorAll()` never match it.
* The bar sits in the browser's top layer (the popover API), above anything the site can stack,
  whatever its `z-index`, `transform` or `overflow`. Browsers without popover support keep it fixed
  at the highest `z-index`.

The site's own fixed and sticky elements at the top of the page, such as a sticky header, are not
moved. The bar sits over them while expanded. Members can collapse the bar to a small tab at the top
of the page (button or <kbd>Esc</kbd>). The page then returns to its normal position, and the choice is
remembered in the `betamask_bar_collapsed` cookie.

Dropdown menus are never clipped: while one is open, the iframe grows to fit it, with a transparent
background. A click outside the menu closes it.

The bar shows whether the page is the draft or the published version. The **Draft / Published**
toggle reloads the page with `?stage=Stage` or `?stage=Live`.

The bar is only added to top-level `GET` navigations to HTML pages. These are always left untouched:

* `fetch()`/XHR calls, sub-resources, frames, and anything that does not accept HTML
* responses that are not HTML pages: redirects, files, JSON, XML, and HTML fragments
* URLs with a file extension (`sitemap.xml`, `robots.txt`, downloads) and `assets/`
* the CMS (`admin/`), `dev/`, the Security login, logout and password pages, and the CMS preview panel

Pages that carry the bar are marked as uncacheable, as they are personalised. Pages for every other
visitor are not changed in any way.

A site with a Content Security Policy needs `'self'` allowed for scripts, styles and frames.

## Configuration

```yaml
SilverStripe\Betamask\Frontend\TopBar\TopBarUI:
  # Never show the bar under these URL prefixes (relative to the site root)
  disable_on_url:
    - 'shop/checkout/'

SilverStripe\Betamask\Frontend\TopBar\TopBarRenderer:
  # Label shown for each environment type; others show "uat" or "test"
  environments:
    dev: 'dev'
    live: 'prod'
```

## Extending the bar

### Menu items

Apply an extension to `TopBarMenu` and implement `updateItems()`. Items are keyed by id. The built-in
ids are available as constants: `ITEM_EDIT`, `ITEM_SETTINGS`, `ITEM_HELP` and `ITEM_PROFILE`.

```php
use SilverStripe\Betamask\Frontend\TopBar\TopBarMenu;
use SilverStripe\Core\Extension;

class TopBarMenuExtension extends Extension
{
    protected function updateItems(array &$items): void
    {
        // A dropdown
        $items['reports'] = [
            'label' => 'Reports',
            'icon' => 'font-icon-chart-line',
            'items' => [
                ['label' => 'Broken links', 'link' => '/admin/reports/broken-links'],
                ['label' => 'Analytics', 'link' => 'https://analytics.example.com', 'target' => '_blank'],
            ],
        ];

        // A link added to an existing dropdown
        $items[TopBarMenu::ITEM_HELP]['items'][] = ['label' => 'Style guide', 'link' => '/style-guide'];

        // Remove an item
        unset($items[TopBarMenu::ITEM_SETTINGS]);
    }
}
```

```yaml
SilverStripe\Betamask\Frontend\TopBar\TopBarMenu:
  extensions:
    - App\Extension\TopBarMenuExtension
```

The array order is the display order. Each item supports:

| Key | Description |
|---|---|
| `label` | Required. Visible text. |
| `link` | Makes the item a plain link instead of a dropdown. |
| `target` | Link target. Defaults to `_top`, the page; use `_blank` for a new tab. |
| `icon` | Admin font icon class, e.g. `font-icon-chart-line`. |
| `items` | Dropdown entries, each with `label`, `link` and optional `target`. |
| `heading` | HTML shown at the top of the dropdown. It is rendered as-is, so only pass trusted markup. |
| `css_modifier` | Modifier for the item's `topbar__action--*` class. |

Items without a label, and dropdown entries without a label or link, are dropped.

### Turning the bar off for more requests

Apply an extension to `TopBarUI` and implement `updateIsEnabled(bool &$enabled)`. `getOwner()->getRequest()`
gives you the request being considered.

### Edit page link

The **Edit page** link uses the CMS edit link of the record returned by the page controller's
`data()`. Any controller can provide its own link instead by implementing `getPageEditUrl(): string`.

## Development

```sh
nvm use          # Node 20
yarn install
yarn build       # writes client/dist, which is committed
yarn lint
```

Run the PHP tests from a project that has the module installed:

```sh
vendor/bin/phpunit vendor/silverstripeltd/betamask-bao/tests
```
