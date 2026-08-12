<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener;

use Contao\CoreBundle\Event\CloseAccountEvent;
use HeimrichHannot\QnaBundle\Service\MemberDataEraser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class CloseAccountEventListener
{
    private const string DELETE_MODE = 'close_delete';

    public function __construct(private MemberDataEraser $memberDataEraser)
    {
    }

    public function __invoke(CloseAccountEvent $event): void
    {
        if (self::DELETE_MODE !== ($event->getContentModel()->row()['reg_close'] ?? null)) {
            return;
        }

        $this->memberDataEraser->erase((int) $event->getMember()->id);
    }
}
