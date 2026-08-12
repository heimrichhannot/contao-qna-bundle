<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class SessionNotPublishedException extends QnaDomainException
{
    public function __construct(int $sessionId)
    {
        parent::__construct(\sprintf('Q&A session %d is not published.', $sessionId));
    }
}
