<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class QuestionTooLongException extends QnaDomainException
{
    public function __construct(int $maximumLength)
    {
        parent::__construct(\sprintf('The question exceeds the maximum length of %d characters.', $maximumLength));
    }

    public function translationKey(): string
    {
        return 'qna.error.question_too_long';
    }
}
