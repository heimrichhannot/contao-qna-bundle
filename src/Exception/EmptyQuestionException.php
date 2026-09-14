<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class EmptyQuestionException extends QnaDomainException
{
    public function __construct()
    {
        parent::__construct('The question must not be empty.');
    }

    public function translationKey(): string
    {
        return 'qna.error.empty_question';
    }
}
