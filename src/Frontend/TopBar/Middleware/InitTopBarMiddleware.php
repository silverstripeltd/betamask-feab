<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar\Middleware;

use SilverStripe\Betamask\Frontend\TopBar\FrameRequest;
use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTP;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Injector\Injectable;

/**
 * Answers top-level page navigations from members who have the bar enabled with
 * the top bar wrapper. The page itself is only rendered once, by the wrapper's
 * iframe, so its status code, headers and side effects all belong to that request.
 */
class InitTopBarMiddleware implements HTTPMiddleware
{

    use Injectable;

    public const string RESPONSE_HEADER = 'X-Betamask';

    public function process(HTTPRequest $request, callable $delegate): HTTPResponse
    {
        $app = TopBarUI::create()->setRequest($request);

        if (!$app->isEnabled()) {
            $response = $delegate($request);

            return $this->keepRedirectInFrame($request, $response);
        }

        // The wrapper is personalised per member and must never be cached
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        $app->setIframeUrl($this->getFrameUrl($request, $app->getFrameStage()));

        return HTTPResponse::create($app->getInterface(), 200)
            ->addHeader('Content-Type', 'text/html; charset=utf-8')
            ->addHeader(self::RESPONSE_HEADER, '1');
    }

    /**
     * Absolute URL of the current request, marked for the iframe
     */
    protected function getFrameUrl(HTTPRequest $request, ?string $stage): string
    {
        $vars = $request->getVars();
        $vars[FrameRequest::INLINE_PREVIEW_PARAM] = '1';

        if ($stage !== null) {
            $vars['stage'] = $stage;
        }

        return Director::absoluteURL($request->getURL()) . '?' . http_build_query($vars);
    }

    /**
     * Carries the frame marker through same-site redirects issued inside the
     * iframe (e.g. after a form submission), so the redirected page is not
     * mistaken for a top-level navigation by browsers that send no Fetch
     * Metadata headers, such as on plain-HTTP development sites.
     */
    protected function keepRedirectInFrame(HTTPRequest $request, HTTPResponse $response): HTTPResponse
    {
        $location = (string) $response->getHeader('Location');

        if (!$request->requestVar(FrameRequest::INLINE_PREVIEW_PARAM)
            || !$response->isRedirect()
            || $location === ''
            || !Director::is_site_url($location)
        ) {
            return $response;
        }

        return $response->addHeader(
            'Location',
            HTTP::setGetVar(FrameRequest::INLINE_PREVIEW_PARAM, '1', $location),
        );
    }

}
