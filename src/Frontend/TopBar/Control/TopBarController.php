<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar\Control;

use SilverStripe\Betamask\Frontend\TopBar\TopBarRenderer;
use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;

/**
 * Serves the admin tool bar document that the loader script shows in an iframe
 * on top of the page. Only members with the bar turned on can load it.
 */
class TopBarController extends Controller
{

    /**
     * Route of the bar document, relative to the site base URL
     */
    public const string ROUTE = 'betamask-bar';

    public const string EDIT_PARAM = 'edit';

    public const string STAGE_PARAM = 'stage';

    private const array STAGES = ['Stage', 'Live'];

    private const array URL_SCHEMES = ['http', 'https'];

    /**
     * @var array<int, string>
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    private static $allowed_actions = [];

    public function index(HTTPRequest $request): HTTPResponse
    {
        if (!TopBarUI::isAvailableToCurrentMember()) {
            $this->httpError(403);
        }

        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        $body = TopBarRenderer::singleton()->render(
            $this->getEditUrl($request),
            $this->getStage($request),
            TopBarUI::create()->isCollapsed(),
        );

        return HTTPResponse::create($body, 200)
            ->addHeader('Content-Type', 'text/html; charset=utf-8')
            ->addHeader('X-Robots-Tag', 'noindex');
    }

    /**
     * The edit link from the query string, when it points at this site over HTTP(S)
     */
    protected function getEditUrl(HTTPRequest $request): string
    {
        $url = trim((string) $request->getVar(self::EDIT_PARAM));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $isRootRelative = str_starts_with($url, '/') && !str_starts_with($url, '//');

        if ($url === '' || (!$isRootRelative && !in_array($scheme, self::URL_SCHEMES, true))) {
            return '';
        }

        return Director::is_site_url($url)
            ? $url
            : '';
    }

    protected function getStage(HTTPRequest $request): string
    {
        $stage = (string) $request->getVar(self::STAGE_PARAM);

        return in_array($stage, self::STAGES, true)
            ? $stage
            : '';
    }

}
