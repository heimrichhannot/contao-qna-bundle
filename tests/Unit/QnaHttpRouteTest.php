<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use HeimrichHannot\QnaBundle\Controller\QnaActionController;
use HeimrichHannot\QnaBundle\Controller\QnaFrameController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class QnaHttpRouteTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string, string, bool}>
     */
    public static function routeProvider(): iterable
    {
        yield 'reader frame' => [QnaFrameController::class, 'reader', 'contao_qna_reader_frame', false];
        yield 'reader controls' => [QnaFrameController::class, 'readerControls', 'contao_qna_reader_controls', false];
        yield 'stage frame' => [QnaFrameController::class, 'stage', 'contao_qna_stage_questions', false];
        yield 'question' => [QnaActionController::class, 'question', 'contao_qna_question_create', true];
        yield 'vote' => [QnaActionController::class, 'vote', 'contao_qna_vote_create', true];
        yield 'start' => [QnaActionController::class, 'start', 'contao_qna_session_start', true];
        yield 'stop' => [QnaActionController::class, 'stop', 'contao_qna_session_stop', true];
    }

    /** @param class-string $controller */
    #[DataProvider('routeProvider')]
    public function testBundleRoutesUseTheRequiredMethodsAndTokenCheck(
        string $controller,
        string $method,
        string $routeName,
        bool $mutating,
    ): void {
        $attribute = (new \ReflectionMethod($controller, $method))
            ->getAttributes(Route::class)[0]
            ->newInstance();

        self::assertSame($routeName, $attribute->name);
        self::assertSame([$mutating ? 'POST' : 'GET'], $attribute->methods);

        if ($mutating) {
            self::assertTrue($attribute->defaults['_token_check']);
        } else {
            self::assertArrayNotHasKey('_token_check', $attribute->defaults);
        }
    }

    public function testRouteLoaderPlacesAllControllerRoutesInTheFrontendScope(): void
    {
        $routes = file_get_contents(\dirname(__DIR__, 2).'/config/routes.yaml');
        self::assertIsString($routes);
        self::assertStringContainsString('_scope: frontend', $routes);
    }
}
