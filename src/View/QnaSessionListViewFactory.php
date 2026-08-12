<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Dto\QnaSessionListItemView;

final readonly class QnaSessionListViewFactory
{
    public function __construct(private ContentUrlGenerator $contentUrlGenerator)
    {
    }

    /**
     * @param iterable<QnaSession> $sessions
     *
     * @return list<QnaSessionListItemView>
     */
    public function create(iterable $sessions, PageModel $readerPage): array
    {
        $items = [];

        foreach ($sessions as $session) {
            $items[] = new QnaSessionListItemView(
                $session->id,
                $session->title,
                $this->contentUrlGenerator->generate(
                    $readerPage,
                    ['parameters' => \sprintf('/%s', $session->alias)],
                ),
            );
        }

        return $items;
    }
}
