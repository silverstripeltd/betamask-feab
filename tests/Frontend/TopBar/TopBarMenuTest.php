<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar;

use SilverStripe\Betamask\Frontend\TopBar\TopBarMenu;
use SilverStripe\Betamask\Tests\Frontend\TopBar\TopBarMenuTest\MenuExtension;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;

class TopBarMenuTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    public function testDefaultItemsHaveStableIds(): void
    {
        $items = TopBarMenu::create()->getItems();

        $this->assertSame(['edit', 'settings', 'help', 'profile'], array_column($items, 'id'));
        $this->assertSame('Logout', $items[3]['items'][1]['label']);
        $this->assertSame('_top', $items[3]['items'][1]['target']);
    }

    public function testLinksOpenInThePageAndEditPointsAtTheRecord(): void
    {
        $items = TopBarMenu::create()->setEditUrl('/admin/pages/edit/show/5')->getItems();

        $this->assertSame('/admin/pages/edit/show/5', $items[0]['link']);
        $this->assertSame('_top', $items[0]['target']);
        $this->assertSame('_blank', $items[2]['items'][0]['target'] ?? '_blank');
        $this->assertSame('_top', $items[3]['items'][1]['target']);
    }

    public function testExtensionsCanAddChangeAndRemoveItems(): void
    {
        TopBarMenu::add_extension(MenuExtension::class);

        try {
            $items = TopBarMenu::create()->getItems();
        } finally {
            TopBarMenu::remove_extension(MenuExtension::class);
        }

        $this->assertSame(['edit', 'help', 'profile', 'reports'], array_column($items, 'id'));

        $helpEntries = array_column($items[1]['items'], 'label');
        $this->assertContains('Style guide', $helpEntries);
        $this->assertNotContains('Entry without a link', $helpEntries);

        $this->assertSame('Broken links', $items[3]['items'][0]['label']);
    }

    public function testBuiltInItemsCarryTheDesignIconsAndShortLabels(): void
    {
        $this->logInAs($this->createMember(['FirstName' => 'Ada', 'Surname' => 'Lovelace']));

        $items = TopBarMenu::create()->getItems();

        $this->assertStringContainsString('icons/bar/edit.svg', $items[0]['icon_url']);
        $this->assertStringContainsString('icons/bar/mobile-edit.svg', $items[0]['mobile_icon_url']);
        $this->assertSame('Settings', $items[1]['short_label']);
        $this->assertSame('Ada Lovelace', $items[3]['label']);
        $this->assertSame('Ada', $items[3]['short_label']);
    }

    public function testProfileUsername(): void
    {
        $menu = TopBarMenu::create();

        $this->logOut();
        $this->assertSame('Login', $menu->getProfileUsername());

        $this->logInAs($this->createMember(['FirstName' => 'Ada', 'Surname' => 'Lovelace']));
        $this->assertSame('Ada Lovelace', $menu->getProfileUsername());

        $this->logInAs($this->createMember(['FirstName' => 'Ada', 'Surname' => '']));
        $this->assertSame('Ada', $menu->getProfileUsername());

        $this->logInAs($this->createMember(['FirstName' => '', 'Surname' => '', 'Email' => 'grace@example.com']));
        $this->assertSame('grace', $menu->getProfileUsername());
    }

    /**
     * @param array<string, string> $fields
     */
    private function createMember(array $fields): Member
    {
        $member = Member::create(array_merge(['Email' => uniqid('editor', true) . '@example.com'], $fields));
        $member->write();

        return $member;
    }

}
