<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\FrontendUser;
use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Controller\QnaActionController;
use HeimrichHannot\QnaBundle\Domain\Question as QnaQuestion;
use HeimrichHannot\QnaBundle\Domain\Session as QnaSession;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\LockedContextLoader;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use HeimrichHannot\QnaBundle\Security\Voter\QnaSessionControlVoter;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\Service\QuestionService;
use HeimrichHannot\QnaBundle\Service\SessionService;
use HeimrichHannot\QnaBundle\Service\VoteService;
use HeimrichHannot\QnaBundle\View\ReaderViewFactory;
use HeimrichHannot\QnaBundle\View\StageViewFactory;
use HeimrichHannot\QnaBundle\View\TurboResponseFactory;
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
        $controller = $this->createController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@100'), $this->transactionConnection()),
            $gateway,
            $this->uninitializedResponseFactory(),
            $security,
            $urlGenerator,
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
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
        $questionGateway->expects(self::once())->method('findForStage')->with(7, QuestionSort::VOTES)->willReturn([]);
        $security = $this->createMock(Security::class);
        $security->expects(self::exactly(2))->method('isGranted')->willReturn(true);
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@Contao/qna/stage_questions.html.twig',
                self::callback(static fn (array $context): bool => 'qna.error.invalid_transition' === $context['error_translation_key']),
            )
            ->willReturn('<turbo-frame id="qna-session-7-stage"></turbo-frame>');
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/frame');
        $memberSecurity = $this->createStub(Security::class);
        $responseFactory = $this->createResponseFactory(
            $gateway,
            $questionGateway,
            $memberSecurity,
            $security,
            $twig,
            $urlGenerator,
        );
        $controller = $this->createController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@150'), $this->transactionConnection()),
            $gateway,
            $responseFactory,
            $security,
            $urlGenerator,
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->start(7, Request::create('/start?sort=invalid', 'POST'));

        self::assertSame(422, $response->getStatusCode());
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
        $controller = $this->createController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@100'), $this->transactionConnection()),
            $gateway,
            $this->uninitializedResponseFactory(),
            $security,
            $urlGenerator,
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->stop(7, Request::create('/stop', 'POST'));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/_qna/stage/7/questions?sort=votes', $response->headers->get('Location'));
        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function testInvalidStopReturnsUnprocessableStageFrame(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::WAITING, null, null);
        $gateway = $this->createStub(QnaSessionGateway::class);
        $gateway->method('findPublished')->willReturn($session);
        $gateway->method('find')->willReturn($session);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $responseFactory = $this->createResponseFactory($gateway, $questionGateway, $security, $security);
        $controller = $this->createController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@150'), $this->transactionConnection()),
            $gateway,
            $responseFactory,
            $security,
            $this->createUrlGenerator(),
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->stop(7, Request::create('/stop', 'POST'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testCreatedQuestionRedirectsToAListUpdateThatResetsTheForm(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createStub(QnaSessionGateway::class);
        $gateway->method('find')->willReturn($session);
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())->method('findLatestCreatedAt')->with(7, 42)->willReturn(null);
        $questionGateway->expects(self::once())
            ->method('create')
            ->with(7, 42, 'Question', 150)
            ->willReturn(23);
        $memberSecurity = $this->createMemberSecurity(42);
        $questionService = new QuestionService(
            $gateway,
            $questionGateway,
            new FrontendMemberProvider($memberSecurity),
            new MockClock('@150'),
            $this->options(),
            $this->createStub(QnaVoteGateway::class),
            $this->transactionConnection(),
        );
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('contao_qna_reader_frame', ['sessionId' => 7, 'resetQuestionForm' => 1])
            ->willReturn('/_qna/reader/7?resetQuestionForm=1');
        $controller = $this->createController(
            $questionService,
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@150'), $this->transactionConnection()),
            $gateway,
            $this->uninitializedResponseFactory(),
            $this->createStub(Security::class),
            $urlGenerator,
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->question(7, Request::create('/question', 'POST', ['question' => 'Question']));

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('/_qna/reader/7?resetQuestionForm=1', $response->headers->get('Location'));
    }

    public function testRejectedQuestionReturnsUnprocessableReaderFrame(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createStub(QnaSessionGateway::class);
        $gateway->method('find')->willReturn($session);
        $gateway->method('findPublished')->willReturn($session);
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('findForSession')->willReturn([]);
        $memberSecurity = $this->createMemberSecurity(42);
        $questionService = new QuestionService(
            $gateway,
            $questionGateway,
            new FrontendMemberProvider($memberSecurity),
            new MockClock('@150'),
            $this->options(),
            $this->createStub(QnaVoteGateway::class),
            $this->transactionConnection(),
        );
        $controller = $this->createController(
            $questionService,
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@150'), $this->transactionConnection()),
            $gateway,
            $this->createResponseFactory($gateway, $questionGateway, $memberSecurity, $this->createStub(Security::class)),
            $this->createStub(Security::class),
            $this->createUrlGenerator(),
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->question(7, Request::create('/question', 'POST', ['question' => '  ']));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testRejectedVoteReturnsUnprocessableReaderFrame(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::CLOSED, 100, 150);
        $gateway = $this->createStub(QnaSessionGateway::class);
        $gateway->method('find')->willReturn($session);
        $gateway->method('findPublished')->willReturn($session);
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('find')->willReturn(new QnaQuestion(23, 7, 4, 'Question', 110));
        $questionGateway->method('findForSession')->willReturn([]);
        $voteGateway = $this->createMock(QnaVoteGateway::class);
        $voteGateway->expects(self::never())->method('create');
        $memberSecurity = $this->createMemberSecurity(42);
        $voteService = new VoteService(
            new LockedContextLoader($gateway, $questionGateway),
            $voteGateway,
            new FrontendMemberProvider($memberSecurity),
            new MockClock('@150'),
            $this->transactionConnection(),
        );
        $controller = $this->createController(
            $this->uninitializedQuestionService(),
            $voteService,
            new SessionService($gateway, new MockClock('@150'), $this->transactionConnection()),
            $gateway,
            $this->createResponseFactory($gateway, $questionGateway, $memberSecurity, $this->createStub(Security::class)),
            $this->createStub(Security::class),
            $this->createUrlGenerator(),
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->vote(7, 23);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testMissingAuthenticationKeepsUnauthorizedStatus(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $gateway = $this->createStub(QnaSessionGateway::class);
        $gateway->method('find')->willReturn($session);
        $gateway->method('findPublished')->willReturn($session);
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('findForSession')->willReturn([]);
        $memberSecurity = $this->createMemberSecurity(null);
        $questionService = new QuestionService(
            $gateway,
            $questionGateway,
            new FrontendMemberProvider($memberSecurity),
            new MockClock('@150'),
            $this->options(),
            $this->createStub(QnaVoteGateway::class),
            $this->transactionConnection(),
        );
        $controller = $this->createController(
            $questionService,
            $this->uninitializedVoteService(),
            new SessionService($gateway, new MockClock('@150'), $this->transactionConnection()),
            $gateway,
            $this->createResponseFactory($gateway, $questionGateway, $memberSecurity, $this->createStub(Security::class)),
            $this->createStub(Security::class),
            $this->createUrlGenerator(),
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $response = $controller->question(7, Request::create('/question', 'POST', ['question' => 'Question']));

        self::assertSame(401, $response->getStatusCode());
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
        $controller = $this->createController(
            $this->uninitializedQuestionService(),
            $this->uninitializedVoteService(),
            new SessionService($gateway, $this->createStub(ClockInterface::class), $this->transactionConnection()),
            $gateway,
            $this->uninitializedResponseFactory(),
            $security,
            $this->createStub(UrlGeneratorInterface::class),
            (new \ReflectionClass(\HeimrichHannot\QnaBundle\Service\QuestionAnswerService::class))->newInstanceWithoutConstructor(),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $controller->start(7, Request::create('/start', 'POST'));
    }

    public function testAnsweredAndUnansweredActionsPreserveSortAndRedirectPrivately(): void
    {
        foreach ([true, false] as $answered) {
            $session = new QnaSession(7, 'Session', 'session', true, SessionState::OPEN, 100, null);
            $sessions = $this->createStub(QnaSessionGateway::class);
            $sessions->method('findPublished')->willReturn($session);
            $sessions->method('find')->willReturn($session);
            $questions = $this->createMock(QnaQuestionGateway::class);
            $questions->expects(self::once())->method('find')->with(23, true)->willReturn(new QnaQuestion(23, 7, 1, 'Question', 100, !$answered));
            $questions->expects(self::once())->method('setAnswered')->with(23, $answered);
            $security = $this->createMock(Security::class);
            $security->expects(self::once())->method('isGranted')->with(QnaSessionControlVoter::ATTRIBUTE, $session)->willReturn(true);
            $urls = $this->createMock(UrlGeneratorInterface::class);
            $urls->expects(self::once())->method('generate')->with('contao_qna_stage_questions', ['sessionId' => 7, 'sort' => 'time'])->willReturn('/stage?sort=time');
            $controller = $this->createController(
                $this->uninitializedQuestionService(), $this->uninitializedVoteService(), new SessionService($sessions, new MockClock('@100'), $this->transactionConnection()),
                $sessions, $this->uninitializedResponseFactory(), $security, $urls,
                new \HeimrichHannot\QnaBundle\Service\QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $this->transactionConnection()),
            );
            $method = $answered ? 'answered' : 'unanswered';
            $response = $controller->$method(7, 23, Request::create('/action?sort=time', 'POST'));
            self::assertSame(303, $response->getStatusCode());
            self::assertSame('/stage?sort=time', $response->headers->get('Location'));
            self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
            self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        }
    }

    public function testClosedSessionAnswerActionRenders422WithSelectedSort(): void
    {
        $session = new QnaSession(7, 'Session', 'session', true, SessionState::CLOSED, 100, 200);
        $sessions = $this->createStub(QnaSessionGateway::class);
        $sessions->method('findPublished')->willReturn($session);
        $sessions->method('find')->willReturn($session);
        $questions = $this->createMock(QnaQuestionGateway::class);
        $questions->method('find')->willReturn(new QnaQuestion(23, 7, 1, 'Question', 100));
        $questions->expects(self::never())->method('setAnswered');
        $questions->expects(self::once())->method('findForStage')->with(7, QuestionSort::TIME)->willReturn([]);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);
        $controller = $this->createController(
            $this->uninitializedQuestionService(), $this->uninitializedVoteService(), new SessionService($sessions, new MockClock('@100'), $this->transactionConnection()),
            $sessions, $this->createResponseFactory($sessions, $questions, $security, $security), $security, $this->createUrlGenerator(),
            new \HeimrichHannot\QnaBundle\Service\QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $this->transactionConnection()),
        );
        $response = $controller->answered(7, 23, Request::create('/action?sort=time', 'POST'));
        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
    }

    public function testWrongSessionQuestionReturnsNotFound(): void
    {
        $session = new QnaSession(7, 'Session', 'session', true, SessionState::OPEN, 100, null);
        $sessions = $this->createStub(QnaSessionGateway::class);
        $sessions->method('findPublished')->willReturn($session);
        $questions = $this->createMock(QnaQuestionGateway::class);
        $questions->method('find')->willReturn(new QnaQuestion(23, 8, 1, 'Question', 100));
        $questions->expects(self::never())->method('setAnswered');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn(true);
        $controller = $this->createController(
            $this->uninitializedQuestionService(), $this->uninitializedVoteService(), new SessionService($sessions, new MockClock('@100'), $this->transactionConnection()),
            $sessions, $this->uninitializedResponseFactory(), $security, $this->createUrlGenerator(),
            new \HeimrichHannot\QnaBundle\Service\QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $this->transactionConnection()),
        );
        $this->expectException(\Contao\CoreBundle\Exception\PageNotFoundException::class);
        $controller->answered(7, 23, Request::create('/action', 'POST'));
    }

    public function testBothAnswerActionsDenyUnauthorizedOperatorsBeforeMutation(): void
    {
        foreach (['answered', 'unanswered'] as $method) {
            $sessions = $this->createStub(QnaSessionGateway::class);
            $sessions->method('findPublished')->willReturn(new QnaSession(7, 'Session', 'session', true, SessionState::OPEN, 100, null));
            $questions = $this->createMock(QnaQuestionGateway::class);
            $questions->expects(self::never())->method('find');
            $security = $this->createStub(Security::class);
            $security->method('isGranted')->willReturn(false);
            $controller = $this->createController(
                $this->uninitializedQuestionService(), $this->uninitializedVoteService(), new SessionService($sessions, new MockClock('@100'), $this->transactionConnection()),
                $sessions, $this->uninitializedResponseFactory(), $security, $this->createUrlGenerator(),
                new \HeimrichHannot\QnaBundle\Service\QuestionAnswerService(new LockedContextLoader($sessions, $questions), $questions, $this->transactionConnection()),
            );
            try {
                $controller->$method(7, 23, Request::create('/action', 'POST'));
                self::fail('Expected access denial.');
            } catch (AccessDeniedHttpException $exception) {
                self::assertSame(403, $exception->getStatusCode());
            }
        }
    }

    private function transactionConnection(): \Doctrine\DBAL\Connection
    {
        $connection = $this->createStub(\Doctrine\DBAL\Connection::class);
        $connection->method('transactional')->willReturnCallback(static fn (callable $callback): mixed => $callback());

        return $connection;
    }

    private function uninitializedQuestionService(): QuestionService
    {
        return (new \ReflectionClass(QuestionService::class))->newInstanceWithoutConstructor();
    }

    private function uninitializedVoteService(): VoteService
    {
        return (new \ReflectionClass(VoteService::class))->newInstanceWithoutConstructor();
    }

    private function uninitializedResponseFactory(): TestViewServices
    {
        return new TestViewServices(
            (new \ReflectionClass(ReaderViewFactory::class))->newInstanceWithoutConstructor(),
            (new \ReflectionClass(StageViewFactory::class))->newInstanceWithoutConstructor(),
            new TurboResponseFactory(),
            $this->createStub(ContaoCsrfTokenManager::class),
            $this->pollingPolicy(),
        );
    }

    private function createMemberSecurity(?int $memberId): Security
    {
        $security = $this->createStub(Security::class);

        if (null === $memberId) {
            $security->method('getUser')->willReturn(null);

            return $security;
        }

        $member = $this->createStub(FrontendUser::class);
        $member->method('__get')->willReturn($memberId);
        $security->method('getUser')->willReturn($member);

        return $security;
    }

    private function createResponseFactory(
        QnaSessionGateway $sessionGateway,
        QnaQuestionGateway $questionGateway,
        Security $memberSecurity,
        Security $controlSecurity,
        ?Environment $twig = null,
        ?UrlGeneratorInterface $urlGenerator = null,
    ): TestViewServices {
        if (null === $twig) {
            $twig = $this->createStub(Environment::class);
            $twig->method('render')->willReturn('<turbo-frame></turbo-frame>');
        }
        $tokenManager = $this->createStub(ContaoCsrfTokenManager::class);
        $urlGenerator ??= $this->createUrlGenerator();

        return new TestViewServices(
            new ReaderViewFactory(
                $twig,
                $questionGateway,
                new FrontendMemberProvider($memberSecurity),
                $tokenManager,
                $urlGenerator,
                $this->options(),
                $this->pollingPolicy(),
            ),
            new StageViewFactory($twig, $questionGateway, $urlGenerator),
            new TurboResponseFactory(),
            $tokenManager,
            $this->pollingPolicy(),
        );
    }

    private function createController(
        QuestionService $questionService,
        VoteService $voteService,
        SessionService $sessionService,
        QnaSessionGateway $sessionGateway,
        TestViewServices $viewServices,
        Security $security,
        UrlGeneratorInterface $urlGenerator,
        \HeimrichHannot\QnaBundle\Service\QuestionAnswerService $answerService,
    ): QnaActionController {
        return new QnaActionController(
            $questionService,
            $voteService,
            $sessionService,
            $sessionGateway,
            $viewServices->reader,
            $viewServices->stage,
            $viewServices->response,
            $viewServices->csrfTokenManager,
            $viewServices->pollingPolicy,
            $security,
            $urlGenerator,
            $answerService,
        );
    }

    private function options(): QnaOptions
    {
        return new QnaOptions(2500, 500, 20, 4, 16);
    }

    private function pollingPolicy(): PollingPolicy
    {
        return new PollingPolicy($this->options());
    }

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/frame');

        return $urlGenerator;
    }
}

final readonly class TestViewServices
{
    public function __construct(
        public ReaderViewFactory $reader,
        public StageViewFactory $stage,
        public TurboResponseFactory $response,
        public ContaoCsrfTokenManager $csrfTokenManager,
        public PollingPolicy $pollingPolicy,
    ) {
    }
}
