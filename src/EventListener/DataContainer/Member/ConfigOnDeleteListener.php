<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\DataContainer\Member;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\QnaBundle\Service\MemberDataEraser;

#[AsCallback(table: 'tl_member', target: 'config.ondelete')]
final readonly class ConfigOnDeleteListener
{
    public function __construct(private MemberDataEraser $memberDataEraser)
    {
    }

    public function __invoke(DataContainer $dataContainer): void
    {
        if (!$dataContainer->id) {
            return;
        }

        $this->memberDataEraser->erase((int) $dataContainer->id);
    }
}
