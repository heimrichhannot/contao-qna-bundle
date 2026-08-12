<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class QuestionCooldownException extends QnaDomainException
{
    public function __construct(int $retryAfter)
    {
        parent::__construct(\sprintf('Another question can be submitted in %d seconds.', $retryAfter));
    }
}
