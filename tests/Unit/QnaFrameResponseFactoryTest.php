<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Enum\QuestionSort;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Model\QuestionListItem;
use HeimrichHannot\QnaBundle\Model\Session;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\View\Model\ReaderView;
use HeimrichHannot\QnaBundle\View\ReaderViewFactory;
use HeimrichHannot\QnaBundle\View\StageViewFactory;
use HeimrichHannot\QnaBundle\View\TurboResponseFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class QnaFrameResponseFactoryTest extends TestCase
{
    public function testReaderQuestionsFrameIsPrivateAndAnonymousMarkupHasNoToken(): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())
            ->method('findForSession')
            ->with(7, 0)
            ->willReturn([]);
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('getUser')->willReturn(null);
        $tokenManager = $this->createMock(ContaoCsrfTokenManager::class);
        $tokenManager->expects(self::never())->method('getDefaultTokenValue');
        $context = null;
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturnCallback(static function (string $template, array $parameters) use (&$context): string {
                $context = $parameters;

                return '<turbo-frame id="qna-session-7-reader"></turbo-frame>';
            });

        $content = $this->readerFactory($twig, $questionGateway, $security, $tokenManager)->renderQuestions($session);
        $response = (new TurboResponseFactory())->html($content);

        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        self::assertIsArray($context);
        self::assertNull($context['request_token']);
        self::assertInstanceOf(ReaderView::class, $context['view']);
        self::assertFalse($context['view']->showQuestionForm);
        self::assertFalse($context['view']->showVoteButtons);
        self::assertSame(2500, $context['polling_interval']);
    }

    public function testReaderControlsKeepRejectedQuestionAndDoNotLoadQuestions(): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::never())->method('findForSession');
        $security = $this->createMock(Security::class);
        $member = $this->createStub(\Contao\FrontendUser::class);
        $member->method('__get')->willReturn(42);
        $security->expects(self::once())->method('getUser')->willReturn($member);
        $tokenManager = $this->createMock(ContaoCsrfTokenManager::class);
        $tokenManager->expects(self::once())->method('getDefaultTokenValue')->willReturn('token');
        $context = null;
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('@Contao/qna/reader_controls_frame.html.twig', self::isArray())
            ->willReturnCallback(static function (string $template, array $parameters) use (&$context): string {
                $context = $parameters;

                return '<turbo-frame id="qna-session-7-reader"></turbo-frame>';
            });

        $content = $this->readerFactory($twig, $questionGateway, $security, $tokenManager)->renderControls(
            $session,
            'qna.error.question_too_long',
            'Still editable',
        );
        $response = (new TurboResponseFactory())->html($content, 422);

        self::assertSame(422, $response->getStatusCode());
        self::assertIsArray($context);
        self::assertSame('Still editable', $context['question_value']);
        self::assertSame('qna.error.question_too_long', $context['error_translation_key']);
        $view = $context['view'];
        self::assertInstanceOf(ReaderView::class, $view);
        self::assertSame('qna-session-7-questions', $view->questionsFrameId);
        self::assertSame('qna-session-7-controls', $view->controlsContentId);
    }

    public function testReaderMutationUpdateUsesTurboStreamAndCanResetOnlyTheQuestionForm(): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $questionGateway = $this->createStub(QnaQuestionGateway::class);
        $questionGateway->method('findForSession')->willReturn([]);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);
        $context = null;
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('@Contao/qna/reader_update.stream.html.twig', self::isArray())
            ->willReturnCallback(static function (string $template, array $parameters) use (&$context): string {
                $context = $parameters;

                return '<turbo-stream action="update"></turbo-stream>';
            });

        $content = $this->readerFactory(
            $twig,
            $questionGateway,
            $security,
            $this->createStub(ContaoCsrfTokenManager::class),
        )->renderUpdate($session, true);
        $response = (new TurboResponseFactory())->stream($content);

        self::assertSame('text/vnd.turbo-stream.html; charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertIsArray($context);
        self::assertTrue($context['reset_question_form']);
    }

    public function testStageFrameUsesSelectedSortAndLongerClosedInterval(): void
    {
        $session = new Session(7, 'Mobility', 'mobility', true, SessionState::CLOSED, 100, 200);
        $question = new QuestionListItem(11, 7, 4, 'Question', 100, 2, false);
        $answered = new QuestionListItem(12, 7, 4, 'Answered', 101, 1, false, true);
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())
            ->method('findForStage')
            ->with(7, QuestionSort::VOTES)
            ->willReturn([$question, $answered]);
        $context = null;
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturnCallback(static function (string $template, array $parameters) use (&$context): string {
                $context = $parameters;

                return '<turbo-frame id="qna-session-7-stage"></turbo-frame>';
            });
        $factory = new StageViewFactory($twig, $questionGateway, $this->createUrlGenerator());
        $view = $factory->create($session, QuestionSort::VOTES, false);
        $content = $factory->renderFrame($view, null, $this->pollingPolicy()->intervalFor($session->state));
        $response = (new TurboResponseFactory())->html($content);

        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        self::assertIsArray($context);
        self::assertSame([$question], $context['unanswered_questions']);
        self::assertSame([$answered], $context['answered_questions']);
        self::assertSame([], $context['answer_urls']);
        self::assertSame('votes', $context['sort']);
        self::assertSame(10000, $context['polling_interval']);
        self::assertFalse($context['show_start_button']);
        self::assertFalse($context['show_stop_button']);
    }

    private function readerFactory(
        Environment $twig,
        QnaQuestionGateway $questionGateway,
        Security $security,
        ContaoCsrfTokenManager $tokenManager,
    ): ReaderViewFactory {
        return new ReaderViewFactory(
            $twig,
            $questionGateway,
            new FrontendMemberProvider($security),
            $tokenManager,
            $this->createUrlGenerator(),
            $this->options(),
            $this->pollingPolicy(),
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
        $urlGenerator->method('generate')
            ->willReturnCallback(static fn (string $name, array $parameters = []): string => '/'.$name);

        return $urlGenerator;
    }
}
