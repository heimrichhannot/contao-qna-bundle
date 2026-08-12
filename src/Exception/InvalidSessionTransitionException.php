<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

use HeimrichHannot\QnaBundle\Enum\SessionState;

final class InvalidSessionTransitionException extends QnaDomainException
{
    public function __construct(SessionState $from, SessionState $to)
    {
        parent::__construct(\sprintf('Cannot transition a Q&A session from %s to %s.', $from->value, $to->value));
    }
}
