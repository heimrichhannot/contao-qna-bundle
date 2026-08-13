<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use HeimrichHannot\QnaBundle\Dto\QnaQuestionListItem;
use HeimrichHannot\QnaBundle\Dto\QnaReaderView;
use HeimrichHannot\QnaBundle\Dto\QnaSession;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\View\QnaFrameResponseFactory;
use HeimrichHannot\QnaBundle\View\QnaReaderViewFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

final class QnaFrameResponseFactoryTest extends TestCase
{
    public function testReaderFrameIsPrivateAndAnonymousMarkupHasNoToken(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::OPEN, 100, null);
        $sessionGateway = $this->createMock(QnaSessionGateway::class);
        $sessionGateway->expects(self::once())->method('findPublished')->with(7)->willReturn($session);
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

        $factory = new QnaFrameResponseFactory(
            $twig,
            $sessionGateway,
            $questionGateway,
            new FrontendMemberProvider($security),
            new QnaReaderViewFactory(),
            $tokenManager,
            $this->createUrlGenerator(),
            $security,
            2500,
            500,
        );

        $response = $factory->renderReader(7);

        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        self::assertIsArray($context);
        self::assertNull($context['request_token']);
        self::assertInstanceOf(QnaReaderView::class, $context['view']);
        self::assertFalse($context['view']->showQuestionForm);
        self::assertFalse($context['view']->showVoteButtons);
        self::assertSame(2500, $context['polling_interval']);
    }

    public function testStageFrameNormalizesSortAndUsesLongerClosedInterval(): void
    {
        $session = new QnaSession(7, 'Mobility', 'mobility', true, SessionState::CLOSED, 100, 200);
        $sessionGateway = $this->createMock(QnaSessionGateway::class);
        $sessionGateway->expects(self::once())->method('findPublished')->with(7)->willReturn($session);
        $question = new QnaQuestionListItem(11, 7, 4, 'Question', 100, 2, false);
        $questionGateway = $this->createMock(QnaQuestionGateway::class);
        $questionGateway->expects(self::once())
            ->method('findForStage')
            ->with(7, 'votes')
            ->willReturn([$question]);
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('isGranted')->willReturn(false);
        $context = null;
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->willReturnCallback(static function (string $template, array $parameters) use (&$context): string {
                $context = $parameters;

                return '<turbo-frame id="qna-session-7-stage"></turbo-frame>';
            });
        $memberSecurity = $this->createStub(Security::class);

        $factory = new QnaFrameResponseFactory(
            $twig,
            $sessionGateway,
            $questionGateway,
            new FrontendMemberProvider($memberSecurity),
            new QnaReaderViewFactory(),
            $this->createStub(ContaoCsrfTokenManager::class),
            $this->createUrlGenerator(),
            $security,
            2500,
            500,
        );

        $response = $factory->renderStage(7, 'invalid');

        self::assertStringContainsString('private', $response->headers->get('Cache-Control', ''));
        self::assertStringContainsString('no-store', $response->headers->get('Cache-Control', ''));
        self::assertIsArray($context);
        self::assertSame('votes', $context['sort']);
        self::assertSame(10000, $context['polling_interval']);
        self::assertFalse($context['show_start_button']);
        self::assertFalse($context['show_stop_button']);
    }

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')
            ->willReturnCallback(static fn (string $name, array $parameters = []): string => '/'.$name);

        return $urlGenerator;
    }
}
