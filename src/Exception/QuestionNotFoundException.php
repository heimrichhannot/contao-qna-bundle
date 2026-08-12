<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class QuestionNotFoundException extends QnaDomainException
{
    public function __construct(int $questionId)
    {
        parent::__construct(\sprintf('Q&A question %d was not found.', $questionId));
    }
}
