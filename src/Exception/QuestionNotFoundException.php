<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

use Symfony\Component\HttpFoundation\Response;

final class QuestionNotFoundException extends QnaDomainException
{
    public function __construct(int $questionId)
    {
        parent::__construct(\sprintf('Q&A question %d was not found.', $questionId));
    }

    public function translationKey(): string
    {
        return 'qna.error.question_not_found';
    }

    public function statusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
