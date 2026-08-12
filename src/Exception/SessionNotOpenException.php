<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

use HeimrichHannot\QnaBundle\Enum\SessionState;

final class SessionNotOpenException extends QnaDomainException
{
    public function __construct(int $sessionId, SessionState $state)
    {
        parent::__construct(\sprintf('Q&A session %d is %s, not open.', $sessionId, $state->value));
    }
}
