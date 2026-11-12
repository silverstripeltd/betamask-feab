<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Tests\Frontend\TopBar\Extension\InitControllerTest;

use SilverStripe\Control\Controller;
use SilverStripe\Dev\TestOnly;

/**
 * Controller that supplies its own CMS edit link
 */
class EditableController extends Controller implements TestOnly
{

    public function getPageEditUrl(): string
    {
        return '/admin/pages/edit/show/5?a=1&b="2"';
    }

}
