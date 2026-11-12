<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\TopBarUITest;

use SilverStripe\Betamask\Frontend\TopBar\TopBarUI;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Turns the bar off for kiosk screens through the updateIsEnabled hook
 *
 * @extends Extension<TopBarUI>
 * @phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedPassingByReference
 */
class KioskExtension extends Extension implements TestOnly
{

    protected function updateIsEnabled(bool &$enabled): void
    {
        $request = $this->getOwner()->getRequest();

        if (!$request instanceof HTTPRequest || !str_starts_with($request->getURL(), 'kiosk')) {
            return;
        }

        $enabled = false;
    }

}
