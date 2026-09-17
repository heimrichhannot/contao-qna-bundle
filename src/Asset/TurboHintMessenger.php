<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Asset;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Message;
use Contao\PageModel;
use Symfony\Contracts\Translation\TranslatorInterface;

/** The mutable request guard prevents duplicate hints from multiple callbacks. */
final class TurboHintMessenger
{
    private bool $warned = false;

    public function __construct(private readonly TurboEntryAvailability $availability, private readonly ContaoFramework $framework, private readonly TranslatorInterface $translator)
    {
    }

    public function warn(?PageModel $page): void
    {
        if ($this->warned) {
            return;
        }

        if (null !== $page ? $this->availability->isActiveForPage($page) : $this->availability->isActiveAnywhere()) {
            return;
        }

        $this->warned = true;
        $key = null !== $page ? 'qna.backend.turbo_missing_page' : 'qna.backend.turbo_missing_global';
        $this->framework->getAdapter(Message::class)->addInfo($this->translator->trans($key, [], 'contao_default'));
    }
}
