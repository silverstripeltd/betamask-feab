<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\Admin\AdminRootController;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\ViewLayerData;

/**
 * Builds the menu items shown on the right of the top bar.
 *
 * Extensions can add, change, reorder or remove items through
 * updateItems(array &$items). Items are keyed by id; each item is an array of:
 * - label (string, required): visible text
 * - link (string): makes the item a plain link
 * - target (string): link target, "_top" (the page) by default or e.g. "_blank"
 * - icon (string): admin font icon class, e.g. "font-icon-chart-line"
 * - items (array): dropdown entries, each with label, link and optional target
 * - heading (string): trusted HTML shown at the top of the dropdown
 * - css_modifier (string): BEM modifier added to the item's wrapper class
 *
 * Items without a label, and dropdown entries without a label or link, are dropped.
 */
class TopBarMenu
{

    use Extensible;
    use Injectable;

    public const string ITEM_EDIT = 'edit';

    public const string ITEM_SETTINGS = 'settings';

    public const string ITEM_HELP = 'help';

    public const string ITEM_PROFILE = 'profile';

    public const string VERSION_TEMPLATE = 'SilverStripe/Betamask/Frontend/TopBar/TopBarVersion';

    /**
     * Links open in the page, not inside the bar's iframe, unless they say otherwise
     */
    public const string DEFAULT_TARGET = '_top';

    private string $editUrl = '';

    /**
     * CMS edit link of the page the bar is shown on; the Edit page item falls
     * back to the pages section without one
     */
    public function setEditUrl(string $editUrl): self
    {
        $this->editUrl = $editUrl;

        return $this;
    }

    /**
     * Menu items in display order
     *
     * @return array<int, array<string, mixed>>
     */
    public function getItems(): array
    {
        $items = [
            self::ITEM_EDIT => $this->getEditItem(),
            self::ITEM_SETTINGS => $this->getSettingsItem(),
            self::ITEM_HELP => $this->getHelpItem(),
            self::ITEM_PROFILE => $this->getProfileItem(),
        ];
        $this->extend('updateItems', $items);

        return $this->normaliseItems($items);
    }

    /**
     * Name shown on the profile menu: full name, first name, or the email's local part
     */
    public function getProfileUsername(): string
    {
        $member = Security::getCurrentUser();

        if (!$member instanceof Member) {
            return 'Login';
        }

        $name = trim(sprintf('%s %s', $member->FirstName, $member->Surname));

        return $name !== ''
            ? $name
            : explode('@', (string) $member->Email)[0];
    }

    /**
     * Absolute URL of a CMS section
     */
    public function getAdminUrl(string $action): string
    {
        return (string) Director::absoluteURL(AdminRootController::admin_url($action));
    }

    /**
     * @return array<string, string>
     */
    protected function getEditItem(): array
    {
        return [
            'css_modifier' => 'edit',
            'icon' => 'font-icon-edit',
            'label' => 'Edit page',
            'link' => $this->editUrl !== '' ? $this->editUrl : $this->getAdminUrl('pages'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function getSettingsItem(): array
    {
        return [
            'css_modifier' => 'settings',
            'icon' => 'font-icon-menu-settings',
            'label' => 'Site settings',
            'link' => $this->getAdminUrl('settings'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getHelpItem(): array
    {
        $items = [];

        foreach (LeftAndMain::singleton()->getHelpLinks() as $helpLink) {
            $items[] = ['link' => (string) $helpLink->URL, 'label' => (string) $helpLink->Title, 'target' => '_blank'];
        }

        return [
            'css_modifier' => 'help',
            'icon' => 'font-icon-white-question',
            'label' => 'Help',
            'items' => $items,
            'heading' => SSTemplateEngine::execute_template(
                self::VERSION_TEMPLATE,
                ViewLayerData::create(['CMSVersionNumber' => LeftAndMain::singleton()->CMSVersionNumber()]),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getProfileItem(): array
    {
        return [
            'css_modifier' => 'username',
            'icon' => 'font-icon-torso',
            'label' => $this->getProfileUsername(),
            'items' => [
                ['link' => $this->getAdminUrl('myprofile'), 'label' => 'My profile', 'target' => '_blank'],
                ['link' => Security::logout_url(), 'label' => 'Logout', 'target' => '_top'],
            ],
        ];
    }

    /**
     * Drops malformed items and converts the id-keyed map into an ordered list
     *
     * @param array<int|string, mixed> $items
     * @return array<int, array<string, mixed>>
     */
    protected function normaliseItems(array $items): array
    {
        $normalised = [];

        foreach ($items as $key => $item) {
            $id = $this->getItemId($key, $item);

            if (!$this->hasText($item, 'label') || array_key_exists($id, $normalised)) {
                continue;
            }

            $normalised[$id] = array_merge(
                ['target' => self::DEFAULT_TARGET],
                $item,
                ['id' => $id],
                $this->normaliseEntries($item),
            );
        }

        return array_values($normalised);
    }

    /**
     * Dropdown entries of an item, without those missing a label or link
     *
     * @param array<string, mixed> $item
     * @return array<string, array<int, mixed>>
     */
    protected function normaliseEntries(array $item): array
    {
        if (!array_key_exists('items', $item)) {
            return [];
        }

        $entries = array_filter(
            (array) $item['items'],
            fn (mixed $entry): bool => $this->hasText($entry, 'label') && $this->hasText($entry, 'link'),
        );

        return ['items' => array_map(
            static fn (array $entry): array => array_merge(['target' => self::DEFAULT_TARGET], $entry),
            array_values($entries),
        )];
    }

    /**
     * An item's explicit id, or its key in the item map
     */
    private function getItemId(int|string $key, mixed $item): string
    {
        if ($this->hasText($item, 'id')) {
            return (string) $item['id'];
        }

        return (string) $key;
    }

    private function hasText(mixed $item, string $key): bool
    {
        return is_array($item) && is_string($item[$key] ?? null) && trim($item[$key]) !== '';
    }

}
