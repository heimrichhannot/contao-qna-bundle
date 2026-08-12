<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Service;

use Contao\FrontendUser;
use HeimrichHannot\QnaBundle\Exception\AuthenticationRequiredException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class FrontendMemberProvider
{
    public function __construct(private Security $security)
    {
    }

    public function getId(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof FrontendUser || 1 > ($memberId = (int) $user->id)) {
            throw new AuthenticationRequiredException();
        }

        return $memberId;
    }
}
