<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

use Symfony\Component\HttpFoundation\Response;

final class AuthenticationRequiredException extends QnaDomainException
{
    public function __construct()
    {
        parent::__construct('An authenticated Contao front end member is required.');
    }

    public function translationKey(): string
    {
        return 'qna.error.authentication_required';
    }

    public function statusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
