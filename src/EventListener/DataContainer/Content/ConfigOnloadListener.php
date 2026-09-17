<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\DataContainer\Content;

use Contao\ArticleModel;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Asset\TurboHintMessenger;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsCallback(table: 'tl_content', target: 'config.onload')]
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
        if (null === $record || !\in_array($record['type'] ?? null, ['qna_session_list', 'qna_session_reader'], true)) {
            return;
        }

        $page = null;
        $pid = $record['pid'] ?? null;
        if ('tl_article' === ($record['ptable'] ?? null) && (\is_int($pid) || \is_string($pid))) {
            $article = $this->framework->getAdapter(ArticleModel::class)->findById($pid);
            if (null !== $article) {
                $page = $this->framework->getAdapter(PageModel::class)->findWithDetails($article->pid);
            }
        }

        $this->messenger->warn($page);
    }
}
