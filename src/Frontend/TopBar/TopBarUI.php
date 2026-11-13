<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Betamask\Frontend\TopBar\Control\TopBarController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Cookie;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\FeatureFlag\FeatureFlag as FeatureFlagRegistry;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Decides whether the admin tool bar is added to the page for the current
 * request, and describes how the page should embed it.
 *
 * Extensions can implement updateIsEnabled(bool &$enabled) to turn the bar off
 * for further requests; getRequest() exposes the request being considered.
 */
class TopBarUI
{

    use Configurable;
    use Extensible;
    use Injectable;

    /**
     * Cookie holding the member's choice to collapse the bar, shared with the loader script
     */
    public const string COLLAPSED_COOKIE = 'betamask_bar_collapsed';

    /**
     * URL prefixes, relative to the site base URL, that never get the bar.
     * The admin, the bar itself, and the Security login/logout/lost-password
     * URLs are always excluded.
     *
     * @config
     * @var string[]
     */
    private static array $disable_on_url = [
        'assets/',
        'dev/',
        'health/check',
        'Security/changepassword',
    ];

    protected ?HTTPRequest $request = null;

    public function setRequest(HTTPRequest $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * The request being considered, for extensions that vary the bar per URL
     */
    public function getRequest(): ?HTTPRequest
    {
        return $this->request;
    }

    /**
     * Whether the bar should be added to the page for the current request
     */
    public function isEnabled(): bool
    {
        if (!$this->isEligibleRequest()) {
            return false;
        }

        $enabled = self::isAvailableToCurrentMember();
        $this->extend('updateIsEnabled', $enabled);

        return $enabled;
    }

    /**
     * Whether the logged-in member has the bar turned on
     */
    public static function isAvailableToCurrentMember(): bool
    {
        return Security::getCurrentUser() instanceof Member
            && FeatureFlagRegistry::checkFeatureFlag(FeatureFlag::NAME);
    }

    /**
     * Whether the member has collapsed the bar to its tab
     */
    public function isCollapsed(): bool
    {
        return Cookie::get(self::COLLAPSED_COOKIE) === '1';
    }

    /**
     * Site-relative URL of the bar document for a page
     *
     * @param string $editUrl CMS edit link of the page's record, if it has one
     * @param string $stage Reading stage the page was rendered in, if versioned
     */
    public function getBarUrl(string $editUrl, string $stage): string
    {
        $query = array_filter([
            TopBarController::EDIT_PARAM => $editUrl,
            TopBarController::STAGE_PARAM => $stage,
        ]);
        $url = Controller::join_links(Director::baseURL(), TopBarController::ROUTE);

        return $query === []
            ? $url
            : $url . '?' . http_build_query($query);
    }

    /**
     * Whether the request is a page navigation the bar may be added to, before
     * considering who is asking
     */
    protected function isEligibleRequest(): bool
    {
        $request = $this->request;

        return $request instanceof HTTPRequest
            && $request->isGET()
            && !$request->isAjax()
            && FrameRequest::isPageNavigation($request)
            && !$this->isDisabledUrl($request);
    }

    /**
     * Whether the request path starts with a URL that never gets the bar
     */
    protected function isDisabledUrl(HTTPRequest $request): bool
    {
        $currentUrl = $this->normaliseUrl($request->getURL());

        foreach ($this->getDisabledUrls() as $url) {
            $prefix = $this->normaliseUrl($url);

            if ($prefix !== '/' && str_starts_with($currentUrl, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    protected function getDisabledUrls(): array
    {
        $securityConfig = Security::config();

        return array_merge(
            (array) self::config()->get('disable_on_url'),
            [
                AdminRootController::get_admin_route(),
                TopBarController::ROUTE,
                (string) $securityConfig->get('login_url'),
                (string) $securityConfig->get('logout_url'),
                (string) $securityConfig->get('lost_password_url'),
            ],
        );
    }

    /**
     * Reduce a site-relative URL to a lower-case path with a single leading and
     * trailing slash, so configured prefixes compare reliably
     */
    protected function normaliseUrl(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return strtolower('/' . ($path === '' ? '' : $path . '/'));
    }

}
