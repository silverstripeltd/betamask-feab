<?php declare(strict_types=1);

namespace SilverStripe\Betamask\Frontend\TopBar;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Resettable;

/**
 * Details about the page being rendered in the current request, collected while
 * its controllers run and read when the bar is added to the response.
 */
class TopBarContext implements Resettable
{

    use Injectable;

    private string $editUrl = '';

    private string $stage = '';

    /**
     * CMS edit link of the record the page displays, or an empty string
     */
    public function getEditUrl(): string
    {
        return $this->editUrl;
    }

    public function setEditUrl(string $editUrl): self
    {
        $this->editUrl = $editUrl;

        return $this;
    }

    /**
     * Reading stage the page was rendered in ("Stage" or "Live"), or an empty string
     */
    public function getStage(): string
    {
        return $this->stage;
    }

    public function setStage(string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public static function reset(): void
    {
        self::singleton()->setEditUrl('')->setStage('');
    }

}
