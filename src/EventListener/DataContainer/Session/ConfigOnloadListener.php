<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\DataContainer\Session;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use HeimrichHannot\QnaBundle\Asset\TurboHintMessenger;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_qna_session', target: 'config.onload')]
final readonly class ConfigOnloadListener
{
    public function __construct(private RequestStack $requestStack, private TurboHintMessenger $messenger)
    {
    }

    public function __invoke(DataContainer $dataContainer): void
    {
        if (null !== $this->requestStack->getCurrentRequest()) {
            $this->messenger->warn(null);
        }
    }
}
