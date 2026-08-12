<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\FrontendUser;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class QnaSessionControlVoterTest extends TestCase
{
    public function testAuthenticatedFrontendMemberCanControlSession(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createStub(FrontendUser::class));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new QnaSessionControlVoter())->vote($token, null, [QnaSessionControlVoter::ATTRIBUTE]),
        );
    }

    public function testAnonymousUserCannotControlSession(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            (new QnaSessionControlVoter())->vote($token, null, [QnaSessionControlVoter::ATTRIBUTE]),
        );
    }

    public function testOtherAttributesAreNotHandled(): void
    {
        $token = $this->createStub(TokenInterface::class);

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            (new QnaSessionControlVoter())->vote($token, null, ['OTHER_ATTRIBUTE']),
        );
    }
}
