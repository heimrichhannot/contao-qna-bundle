<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

use Symfony\Component\HttpFoundation\Response;

abstract class QnaDomainException extends \RuntimeException
{
    abstract public function translationKey(): string;

    public function statusCode(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
