<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;

final readonly class MemberDataEraser
{
    public function __construct(
        private Connection $connection,
        private QnaQuestionGateway $questionGateway,
        private QnaVoteGateway $voteGateway,
    ) {
    }

    public function erase(int $memberId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($memberId): void {
            $this->voteGateway->deleteByMemberIdOrQuestionAuthor($memberId);
            $this->questionGateway->deleteByMemberId($memberId);
        });
    }
}
