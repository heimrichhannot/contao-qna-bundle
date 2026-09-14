<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

use Symfony\Component\HttpFoundation\Response;

final class SessionNotPublishedException extends QnaDomainException
{
    public function __construct(int $sessionId)
    {
        parent::__construct(\sprintf('Q&A session %d is not published.', $sessionId));
    }

    public function translationKey(): string
    {
        return 'qna.error.session_not_published';
    }

    public function statusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
