<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\TopBarMenuTest;

use SilverStripe\Betamask\Frontend\TopBar\TopBarMenu;
use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

/**
 * Adds, changes and removes top bar items through the updateItems hook
 *
 * @extends Extension<TopBarMenu>
 * @phpcs:disable SlevomatCodingStandard.PHP.DisallowReference.DisallowedPassingByReference
 */
class MenuExtension extends Extension implements TestOnly
{

    /**
     * @param array<string, array<string, mixed>> $items
     */
    protected function updateItems(array &$items): void
    {
        unset($items[TopBarMenu::ITEM_SETTINGS]);

        $items[TopBarMenu::ITEM_HELP]['items'][] = [
            'label' => 'Style guide',
            'link' => '/style-guide',
            'target' => '_blank',
        ];
        $items[TopBarMenu::ITEM_HELP]['items'][] = ['label' => 'Entry without a link'];

        $items['reports'] = [
            'label' => 'Reports',
            'icon' => 'font-icon-chart-line',
            'items' => [
                ['label' => 'Broken links', 'link' => '/admin/reports/broken-links'],
            ],
        ];
        $items['unlabelled'] = ['link' => '/nowhere'];
        $items['not-an-array'] = 'Reports';
    }

}
