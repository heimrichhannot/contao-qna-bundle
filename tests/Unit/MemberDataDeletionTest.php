<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\ContentModel;
use Contao\CoreBundle\Event\CloseAccountEvent;
use Contao\MemberModel;
use Contao\Module;
use Doctrine\DBAL\Connection;
use HeimrichHannot\QnaBundle\EventListener\CloseAccountEventListener;
use HeimrichHannot\QnaBundle\EventListener\Hook\CloseAccountListener;
use HeimrichHannot\QnaBundle\Gateway\QnaQuestionGateway;
use HeimrichHannot\QnaBundle\Gateway\QnaVoteGateway;
use HeimrichHannot\QnaBundle\Service\MemberDataEraser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MemberDataDeletionTest extends TestCase
{
    public function testErasingMemberDataLocksSessionsThenQuestionsBeforeDeletingVotesAndQuestions(): void
    {
        $statements = [];
        $memberDataEraser = $this->createMemberDataEraser($statements);

        $memberDataEraser->erase(42);

        self::assertCount(4, $statements);
        self::assertSame('SELECT id FROM tl_qna_session ORDER BY id FOR UPDATE', $statements[0]['sql']);
        self::assertSame('SELECT id FROM tl_qna_question WHERE memberId = ? ORDER BY id FOR UPDATE', $statements[1]['sql']);
        self::assertSame([42], $statements[1]['params']);
        self::assertStringContainsString('DELETE FROM tl_qna_vote', $statements[2]['sql']);
        self::assertStringContainsString('DELETE FROM tl_qna_question', $statements[3]['sql']);
        self::assertSame(['memberId' => 42], $statements[2]['params']);
        self::assertSame(['memberId' => 42], $statements[3]['params']);
    }

    /**
     * @param class-string<CloseAccountEventListener|CloseAccountListener> $listenerClass
     */
    #[DataProvider('closeAccountListenerProvider')]
    public function testCloseAccountDeletionModeDeletesMemberData(string $listenerClass): void
    {
        $statements = [];
        $memberDataEraser = $this->createMemberDataEraser($statements);

        $this->invokeCloseAccountListener($listenerClass, $memberDataEraser, 'close_delete');

        self::assertCount(4, $statements);
    }

    /**
     * @param class-string<CloseAccountEventListener|CloseAccountListener> $listenerClass
     */
    #[DataProvider('closeAccountListenerProvider')]
    public function testCloseAccountDeactivationModeKeepsMemberData(string $listenerClass): void
    {
        $statements = [];
        $memberDataEraser = $this->createMemberDataEraser($statements);

        $this->invokeCloseAccountListener($listenerClass, $memberDataEraser, 'close_deactivate');

        self::assertSame([], $statements);
    }

    /**
     * @return iterable<string, array{class-string<CloseAccountEventListener|CloseAccountListener>}>
     */
    public static function closeAccountListenerProvider(): iterable
    {
        yield 'event' => [CloseAccountEventListener::class];
        yield 'legacy hook' => [CloseAccountListener::class];
    }

    /**
     * @param list<array{sql: string, params: array<array-key, int>}> $statements
     */
    private function createMemberDataEraser(array &$statements): MemberDataEraser
    {
        $connection = $this->createStub(Connection::class);
        $connection
            ->method('transactional')
            ->willReturnCallback(static fn (\Closure $callback): mixed => $callback($connection))
        ;
        $connection->method('fetchFirstColumn')->willReturnCallback(
            static function (string $sql, array $params = []) use (&$statements): array {
                $statements[] = ['sql' => $sql, 'params' => $params];

                return [];
            },
        );
        $connection
            ->method('executeStatement')
            ->willReturnCallback(
                static function (string $sql, array $params) use (&$statements): int {
                    $statements[] = ['sql' => $sql, 'params' => $params];

                    return 1;
                },
            )
        ;

        return new MemberDataEraser(
            $connection,
            new QnaQuestionGateway($connection),
            new QnaVoteGateway($connection),
        );
    }

    /**
     * @param class-string<CloseAccountEventListener|CloseAccountListener> $listenerClass
     */
    private function invokeCloseAccountListener(
        string $listenerClass,
        MemberDataEraser $memberDataEraser,
        string $mode,
    ): void {
        if (CloseAccountEventListener::class === $listenerClass) {
            $member = $this->createStub(MemberModel::class);
            $member->method('__get')->willReturn(42);

            $contentModel = $this->createStub(ContentModel::class);
            $contentModel->method('row')->willReturn(['reg_close' => $mode]);

            (new CloseAccountEventListener($memberDataEraser))(
                new CloseAccountEvent($member, $contentModel),
            );

            return;
        }

        (new CloseAccountListener($memberDataEraser))(
            42,
            $mode,
            $this->createStub(Module::class),
        );
    }
}
