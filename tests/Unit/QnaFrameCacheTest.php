<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use HeimrichHannot\QnaBundle\Configuration\QnaOptions;
use HeimrichHannot\QnaBundle\Controller\QnaFrameController;
use HeimrichHannot\QnaBundle\Domain\QuestionListItem;
use HeimrichHannot\QnaBundle\Domain\Session;
use HeimrichHannot\QnaBundle\Enum\SessionState;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaSessionGateway;
use HeimrichHannot\QnaBundle\Service\FrontendMemberProvider;
use HeimrichHannot\QnaBundle\Service\PollingPolicy;
use HeimrichHannot\QnaBundle\Tests\Fixtures\TemplateEnvironment;
use HeimrichHannot\QnaBundle\View\ReaderViewFactory;
use HeimrichHannot\QnaBundle\View\StageViewFactory;
use HeimrichHannot\QnaBundle\View\TurboResponseFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class QnaFrameCacheTest extends TestCase
{
    /** @return iterable<string, array{SessionState, bool, bool}> */
    public static function stageCases(): iterable
    {
        foreach (SessionState::cases() as $state) {
            foreach ([false, true] as $control) {
                foreach ([false, true] as $stream) {
                    yield $state->value.'-'.(int) $control.'-'.(int) $stream => [$state, $control, $stream];
                }
            }
        }
    }

    #[DataProvider('stageCases')]
    public function testStageCacheDependsOnRenderedControls(SessionState $state, bool $control, bool $stream): void
    {
        $hasControls = $control && SessionState::CLOSED !== $state;
        $request = Request::create('/_qna/stage/7/questions?sort=time');
        if ($stream) {
            $request->headers->set('Accept', TurboResponseFactory::TURBO_STREAM_CONTENT_TYPE);
        }
        $response = $this->controller($state, $control, $hasControls)->stage(7, $request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame($hasControls ? 'no-store, private' : 'max-age=0, must-revalidate, public, s-maxage=1', $response->headers->get('Cache-Control'));
        self::assertSame(['Accept', 'Accept-Language', 'Cookie', 'Authorization'], $response->getVary());
        self::assertSame(($stream ? TurboResponseFactory::TURBO_STREAM_CONTENT_TYPE : 'text/html').'; charset=UTF-8', $response->headers->get('Content-Type'));
        $html = (string) $response->getContent();
        if ($hasControls) {
            self::assertStringContainsString('secret-token', $html);
            self::assertStringContainsString('REQUEST_TOKEN', $html);
        } else {
            self::assertStringNotContainsString('secret-token', $html);
            self::assertStringNotContainsString('REQUEST_TOKEN', $html);
            self::assertStringNotContainsString('<form', $html);
        }
        if (SessionState::WAITING !== $state) {
            self::assertStringContainsString('id="qna-session-7-sort-votes"', $html);
            self::assertStringContainsString('id="qna-session-7-sort-time"', $html);
            self::assertStringNotContainsString('data-turbo-stream', $html);
        }
    }

    public function testCookiesAuthorizationAndShortPollingDisableSharing(): void
    {
        $cookie = Request::create('/_qna/stage/7/questions', cookies: ['session' => 'member']);
        $authorization = Request::create('/_qna/stage/7/questions');
        $authorization->headers->set('Authorization', 'Bearer example');
        foreach ([$cookie, $authorization] as $request) {
            self::assertSame('no-store, private', $this->controller()->stage(7, $request)->headers->get('Cache-Control'));
        }
        self::assertSame('no-store, private', $this->controller(interval: 1000)->stage(7, Request::create('/'))->headers->get('Cache-Control'));
    }

    public function testReaderHtmlStreamsAndControlsRemainPrivate(): void
    {
        foreach (['text/html', TurboResponseFactory::TURBO_STREAM_CONTENT_TYPE] as $accept) {
            $request = Request::create('/_qna/reader/7');
            $request->headers->set('Accept', $accept);
            self::assertSame('no-store, private', $this->controller()->reader(7, $request)->headers->get('Cache-Control'));
        }
        self::assertSame('no-store, private', $this->controller()->readerControls(7)->headers->get('Cache-Control'));
    }

    public function testAuthenticatedReaderResponsesContainTokenAndRemainPrivate(): void
    {
        foreach (['text/html', TurboResponseFactory::TURBO_STREAM_CONTENT_TYPE] as $accept) {
            $request = Request::create('/_qna/reader/7');
            $request->headers->set('Accept', $accept);
            $response = $this->controller(expectToken: true, member: true)->reader(7, $request);
            self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
            self::assertStringContainsString('secret-token', (string) $response->getContent());
        }
        $response = $this->controller(expectToken: true, member: true)->readerControls(7);
        self::assertSame('no-store, private', $response->headers->get('Cache-Control'));
        self::assertStringContainsString('secret-token', (string) $response->getContent());
    }

    private function controller(SessionState $state = SessionState::OPEN, bool $control = false, bool $expectToken = false, int $interval = 2500, bool $member = false): QnaFrameController
    {
        $sessions = $this->createStub(QnaSessionGateway::class);
        $sessions->method('findPublished')->willReturn(new Session(7, 'Public', 'public', true, $state, null, null));
        $questions = $this->createStub(QnaQuestionGateway::class);
        $questions->method('findForStage')->willReturn([new QuestionListItem(23, 7, 42, 'Question', 100, 3, false)]);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($control);
        $user = $member ? $this->createStub(\Contao\FrontendUser::class) : null;
        $user?->method('__get')->willReturn(42);
        $security->method('getUser')->willReturn($user);
        $tokens = $this->createMock(ContaoCsrfTokenManager::class);
        $tokens->expects($expectToken ? self::once() : self::never())->method('getDefaultTokenValue')->willReturn('secret-token');
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(static fn (string $route): string => '/'.$route);
        $twig = TemplateEnvironment::create();
        $options = new QnaOptions($interval, 500, 20, 4, 16);
        $polling = new PollingPolicy($options);

        return new QnaFrameController(
            $sessions,
            new ReaderViewFactory($twig, $questions, new FrontendMemberProvider($security), $tokens, $urls, $options, $polling),
            new StageViewFactory($twig, $questions, $urls),
            new TurboResponseFactory(),
            $tokens,
            $security,
            $polling,
        );
    }
}
