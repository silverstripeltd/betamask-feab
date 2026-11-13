<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar\Middleware;

use SilverStripe\Betamask\Frontend\TopBar\TopBarContext;
use SilverStripe\Betamask\Frontend\TopBar\TopBarInjector;
use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Middleware\HTTPCacheControlMiddleware;
use SilverStripe\Control\Middleware\HTTPMiddleware;
use SilverStripe\Core\Injector\Injectable;

/**
 * Adds the admin tool bar to front-end HTML pages for members who have it
 * turned on. The page is rendered normally; the bar is added afterwards.
 */
class InitTopBarMiddleware implements HTTPMiddleware
{

    use Injectable;

    public function process(HTTPRequest $request, callable $delegate): HTTPResponse
    {
        $app = TopBarUI::create()->setRequest($request);

        if (!$app->isEnabled()) {
            return $delegate($request);
        }

        // Pages carrying the bar are personalised and must never be cached
        HTTPCacheControlMiddleware::singleton()->disableCache(true);

        $response = $delegate($request);
        $injector = TopBarInjector::singleton();

        if (!$injector->canInject($response)) {
            return $response;
        }

        $context = TopBarContext::singleton();
        $barUrl = $app->getBarUrl($context->getEditUrl(), $context->getStage());

        return $injector->inject($response, $barUrl, $app->isCollapsed());
    }

}
