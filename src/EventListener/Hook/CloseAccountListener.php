<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\Hook;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Module;
use HeimrichHannot\QnaBundle\Service\MemberDataEraser;

#[AsHook('closeAccount')]
final readonly class CloseAccountListener
{
    private const string DELETE_MODE = 'close_delete';

    public function __construct(private MemberDataEraser $memberDataEraser)
    {
    }

    public function __invoke(int $memberId, string $mode, Module $module): void
    {
        if (self::DELETE_MODE !== $mode) {
            return;
        }

        $this->memberDataEraser->erase($memberId);
    }
}
