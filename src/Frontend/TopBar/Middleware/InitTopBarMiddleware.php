<?php

namespace SilverStripe\Betamask\Frontend\TopBar\Middleware;

use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Control\Middleware\HTTPMiddleware;

/**
 * @phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
 */
class InitTopBarMiddleware implements HTTPMiddleware
{

    public function process(HTTPRequest $request, callable $delegate)
    {
        // Ensure cache disabled
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        // Instantiate Topbar interface
        $app = TopBarUI::create();
        $app->setRequest($request);

        // Check if UI enabled
        if (!$app->isEnabled()) {
            return $delegate($request);
        }

        // Clone http request and modify it for iframe
        $clone = clone $request;
        $clone->offsetSet('InlinePreview', 1);
        $clone->offsetSet('stage', 'Stage');
        $app->setIframeUrl(Director::absoluteURL($clone->getURL(true)));

        // Instantiate http response to return
        $response = $delegate($clone);
        assert($response instanceof HTTPResponse);

        // Optional add header to indicate we are betamask
        $response->addHeader('X-Betamask', '1');

        // Set betamask interface and return response
        $response->setBody($app->getInterface());
        $response->setStatusCode(200);

        return $response;
    }

}
