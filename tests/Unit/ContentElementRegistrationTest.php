<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\Controller\ContentElement\AbstractContentElementController;
use Contao\CoreBundle\DependencyInjection\Attribute\AsContentElement;
use HeimrichHannot\QnaBundle\Controller\ContentElement\QnaSessionListController;
use HeimrichHannot\QnaBundle\Controller\ContentElement\QnaSessionReaderController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContentElementRegistrationTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function contentElementProvider(): iterable
    {
        yield 'session list' => [QnaSessionListController::class, 'qna_session_list'];
        yield 'session reader' => [QnaSessionReaderController::class, 'qna_session_reader'];
    }

    /**
     * @param class-string $controllerClass
     */
    #[DataProvider('contentElementProvider')]
    public function testContentElementsUseTheDedicatedQnaCategory(
        string $controllerClass,
        string $expectedType,
    ): void {
        $reflection = new \ReflectionClass($controllerClass);
        $attributes = $reflection->getAttributes(AsContentElement::class);

        self::assertTrue($reflection->isSubclassOf(AbstractContentElementController::class));
        self::assertCount(1, $attributes);

        $registration = $attributes[0]->newInstance();
        self::assertSame($expectedType, $registration->attributes['type']);
        self::assertSame('qna', $registration->attributes['category']);
    }
}
