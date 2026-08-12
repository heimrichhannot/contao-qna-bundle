<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Security\Voter;

use Contao\FrontendUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Host projects can replace this service to apply stricter control rules.
 *
 * @extends Voter<string, mixed>
 */
class QnaSessionControlVoter extends Voter
{
    public const string ATTRIBUTE = 'QNA_SESSION_CONTROL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::ATTRIBUTE === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        return $token->getUser() instanceof FrontendUser;
    }
}
