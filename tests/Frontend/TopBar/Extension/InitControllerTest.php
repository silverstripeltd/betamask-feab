<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\Extension;

use SilverStripe\Betamask\Frontend\TopBar\TopBarContext;
use SilverStripe\Betamask\Tests\Frontend\TopBar\Extension\InitControllerTest\EditableController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;

class InitControllerTest extends SapphireTest
{

    /**
     * @var bool
     * @phpcs:disable SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingNativeTypeHint
     */
    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();

        TopBarContext::reset();
    }

    public function testRecordsEditLinkAndStage(): void
    {
        $this->logInMember(true);

        Versioned::withVersionedMode(function (): void {
            Versioned::set_stage(Versioned::DRAFT);
            $this->runInitHook(EditableController::create());
        });

        $context = TopBarContext::singleton();
        $this->assertSame(EditableController::EDIT_URL, $context->getEditUrl());
        $this->assertSame(Versioned::DRAFT, $context->getStage());
    }

    public function testControllerWithoutRecordKeepsTheEditLink(): void
    {
        $this->logInMember(true);

        $this->runInitHook(EditableController::create());
        $this->runInitHook(Controller::create());

        $this->assertSame(EditableController::EDIT_URL, TopBarContext::singleton()->getEditUrl());
    }

    public function testRecordsNothingForMembersWithoutTheBar(): void
    {
        $this->logInMember(false);

        $this->runInitHook(EditableController::create());

        $this->assertSame('', TopBarContext::singleton()->getEditUrl());
    }

    private function runInitHook(Controller $controller): void
    {
        $controller->setRequest(new HTTPRequest('GET', 'page'));
        $controller->extend('onBeforeInit');
    }

    private function logInMember(bool $flagged): void
    {
        $member = Member::create([
            'Email' => uniqid('editor', true) . '@example.com',
            'FeatureFlag_BetamaskFrontend' => $flagged,
        ]);
        $member->write();
        $this->logInAs($member);
    }

}
