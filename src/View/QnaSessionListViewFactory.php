<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\View;

use Contao\CoreBundle\Routing\ContentUrlGenerator;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\View\Model\SessionListItemView;

final readonly class QnaSessionListViewFactory
{
    public function __construct(private ContentUrlGenerator $contentUrlGenerator)
    {
    }

    /**
     * @param iterable<Session> $sessions
     *
     * @return list<SessionListItemView>
     */
    public function create(iterable $sessions, PageModel $readerPage): array
    {
        $items = [];

        foreach ($sessions as $session) {
            $items[] = new SessionListItemView(
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
