<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use HeimrichHannot\QnaBundle\Controller\QnaActionController;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use HeimrichHannot\QnaBundle\Service\SessionService;
use HeimrichHannot\QnaBundle\Service\VoteService;
use HeimrichHannot\QnaBundle\View\QnaFrameResponseFactory;
use HeimrichHannot\QnaBundle\View\QnaReaderViewFactory;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class QnaActionControllerTest extends TestCase
{
    public function testStartChecksVoterUsesSessionServiceAndReturnsPrivatePrgResponse(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::WAITING, null, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('findPublished')->with(7)->willReturn($session);
        $gateway->expects(self::once())->method('find')->with(7)->willReturn($session);
        $gateway->expects(self::once())->method('markOpen')->with(7, 100)->willReturn(true);
        $security = $this->createMock(Security::class);
        $security->expects(self::once())
            ->method('isGranted')
            ->with(QnaSessionControlVoter::ATTRIBUTE, $session)
            ->willReturn(true);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('contao_qna_stage_questions', ['sessionId' => 7, 'sort' => 'time'])
            ->willReturn('/_qna/stage/7/questions?sort=time');
        $controller = new QnaActionController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@100')),
            $gateway,
            $this->uninitializedResponseFactory(),
            $security,
            $urlGenerator,
        );

        $response = $controller->start(7, Request::create('/start?sort=time', 'POST'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/_qna/stage/7/questions?sort=time', $response->headers->get('Location'));
        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function testClosedSessionCannotBeStartedThroughTheHttpAction(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::CLOSED, 50, 100);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::exactly(2))->method('findPublished')->with(7)->willReturn($session);
        $gateway->expects(self::once())->method('find')->with(7)->willReturn($session);
        $gateway->expects(self::never())->method('markOpen');
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())->method('findForStage')->with(7, 'votes')->willReturn([]);
        $security = $this->createMock(Security::class);
        $security->expects(self::exactly(2))->method('isGranted')->willReturn(true);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@HeimrichHannotQna/qna/stage_questions.html.twig',
                self::callback(static fn (array $context): bool => 'qna.error.invalid_transition' === $context['error_translation_key']),
            )
            ->willReturn('<turbo-frame id="qna-session-7-stage"></turbo-frame>');
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/frame');
        $memberSecurity = $this->createStub(Security::class);
        $responseFactory = new QnaFrameResponseFactory(
            $twig,
            $gateway,
            $questionGateway,
            new FrontendMemberProvider($memberSecurity),
            new QnaReaderViewFactory(),
            $this->createStub(ContaoCsrfTokenManager::class),
            $urlGenerator,
            $security,
            2500,
            500,
        );
        $controller = new QnaActionController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@150')),
            $gateway,
            $responseFactory,
            $security,
            $urlGenerator,
        );

        $response = $controller->start(7, Request::create('/start?sort=invalid', 'POST'));

        self::assertSame(409, $response->getStatusCode());
        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function testStopChecksVoterUsesSessionServiceAndReturnsPrivatePrgResponse(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 50, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('findPublished')->with(7)->willReturn($session);
        $gateway->expects(self::once())->method('find')->with(7)->willReturn($session);
        $gateway->expects(self::once())->method('markClosed')->with(7, 100)->willReturn(true);
        $security = $this->createMock(Security::class);
        $security->expects(self::once())
            ->method('isGranted')
            ->with(QnaSessionControlVoter::ATTRIBUTE, $session)
            ->willReturn(true);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('contao_qna_stage_questions', ['sessionId' => 7, 'sort' => 'votes'])
            ->willReturn('/_qna/stage/7/questions?sort=votes');
        $controller = new QnaActionController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@100')),
            $gateway,
            $this->uninitializedResponseFactory(),
            $security,
            $urlGenerator,
        );

        $response = $controller->stop(7, Request::create('/stop', 'POST'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/_qna/stage/7/questions?sort=votes', $response->headers->get('Location'));
        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function testStartRejectsMissingControlPermissionBeforeMutation(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::WAITING, null, null);
        $gateway = $this->createMock(QnaSessionGateway::class);
        $gateway->expects(self::once())->method('findPublished')->with(7)->willReturn($session);
        $gateway->expects(self::never())->method('find');
        $gateway->expects(self::never())->method('markOpen');
        $security = $this->createMock(Security::class);
        $security->expects(self::once())
            ->method('isGranted')
            ->with(QnaSessionControlVoter::ATTRIBUTE, $session)
            ->willReturn(false);
        $controller = new QnaActionController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, $this->createStub(ClockInterface::class)),
            $gateway,
            $this->uninitializedResponseFactory(),
            $security,
            $this->createStub(UrlGeneratorInterface::class),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $controller->start(7, Request::create('/start', 'POST'));
    }

    private function uninitializedQuestionService(): QuestionService
    {
        return (new \ReflectionClass(QuestionService::class))->newInstanceWithoutConstructor();
    }

    private function uninitializedVoteService(): VoteService
    {
        return (new \ReflectionClass(VoteService::class))->newInstanceWithoutConstructor();
    }

    private function uninitializedResponseFactory(): QnaFrameResponseFactory
    {
        return (new \ReflectionClass(QnaFrameResponseFactory::class))->newInstanceWithoutConstructor();
    }
}
