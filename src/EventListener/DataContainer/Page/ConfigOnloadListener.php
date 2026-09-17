<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\DataContainer\Page;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Asset\TurboHintMessenger;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_page', target: 'config.onload')]
final readonly class ConfigOnloadListener
{
    public function __construct(private RequestStack $requestStack, private ContaoFramework $framework, private TurboHintMessenger $messenger)
    {
    }

    public function __invoke(DataContainer $dataContainer): void
    {
        if ('edit' !== $this->requestStack->getCurrentRequest()?->query->get('act')) {
            return;
        }

        $record = $dataContainer->getCurrentRecord();
        if (null === $record || 'qna_stage' !== ($record['type'] ?? null)) {
            return;
        }

        $id = $record['id'] ?? null;
        $page = \is_int($id) || \is_string($id) ? $this->framework->getAdapter(PageModel::class)->findWithDetails($id) : null;
        $this->messenger->warn($page);
    }
}
