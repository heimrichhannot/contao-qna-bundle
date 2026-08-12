<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Exception;

final class AuthenticationRequiredException extends QnaDomainException
{
    public function __construct()
    {
        parent::__construct('An authenticated Contao front end member is required.');
    }
}
