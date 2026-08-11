<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\EventListener\DataContainer\Member;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Repository\QnaQuestionRepository;
use HeimrichHannot\QnaBundle\Repository\QnaVoteRepository;

#[AsCallback(table: 'tl_member', target: 'config.ondelete')]
final readonly class ConfigOnDeleteListener
{
    public function __construct(
        private Connection $connection,
        private QnaQuestionRepository $questionRepository,
        private QnaVoteRepository $voteRepository,
    ) {
    }

    public function __invoke(DataContainer $dataContainer): void
    {
        if (!$dataContainer->id) {
            return;
        }

        $memberId = (int) $dataContainer->id;

        $this->connection->transactional(function () use ($memberId): void {
            $this->voteRepository->deleteByMemberIdOrQuestionAuthor($memberId);
            $this->questionRepository->deleteByMemberId($memberId);
        });
    }
}
