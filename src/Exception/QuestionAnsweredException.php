<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class QuestionAnsweredException extends QnaDomainException
{
    public function translationKey(): string
    {
        return 'qna.error.question_answered';
    }
}
