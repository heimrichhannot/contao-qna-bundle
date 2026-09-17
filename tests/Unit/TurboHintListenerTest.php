<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\ArticleModel;
use Contao\CoreBundle\Framework\Adapter;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\DataContainer;
use Contao\LayoutModel;
use Contao\Message;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\MySQLSchemaManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use HeimrichHannot\ContaoUxTurboEncore\EncoreExtension;
use HeimrichHannot\QnaBundle\Asset\TurboEntryAvailability;
use HeimrichHannot\QnaBundle\Asset\TurboHintMessenger;
use HeimrichHannot\QnaBundle\EventListener\DataContainer\Content\ConfigOnloadListener as ContentListener;
use HeimrichHannot\QnaBundle\EventListener\DataContainer\Page\ConfigOnloadListener as PageListener;
use HeimrichHannot\QnaBundle\EventListener\DataContainer\Session\ConfigOnloadListener as SessionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TurboHintListenerTest extends TestCase
{
    #[DataProvider('listenerCases')]
    public function testListeners(string $listenerType, ?string $act, ?string $type, string $ptable, bool $active, ?string $messageKey): void
    {
        $stack = new RequestStack();
        if (null !== $act) {
            $stack->push(new Request(['act' => $act]));
        }
        $dc = $this->createMock(DataContainer::class);
        $dc->expects('session' !== $listenerType && 'edit' === $act ? self::exactly(2) : self::never())->method('getCurrentRecord')
            ->willReturn(null === $type ? null : ['id' => 3, 'type' => $type, 'pid' => 7, 'ptable' => $ptable]);
        $eligible = null !== $act && ('session' === $listenerType || ('edit' === $act && (('content' === $listenerType && \in_array($type, ['qna_session_reader', 'qna_session_list'], true)) || ('page' === $listenerType && 'qna_stage' === $type))));
        $pageSpecific = $eligible && ('page' === $listenerType || ('content' === $listenerType && 'tl_article' === $ptable));
        $page = $this->createStub(PageModel::class);
        $page->method('__get')->willReturnMap([['layout', 5], ['trail', []], ['id', 3]]);
        $page->method('row')->willReturn([]);
        $layout = $this->createStub(LayoutModel::class);
        $blob = serialize([['entry' => $active ? EncoreExtension::DEFAULT : 'huh_qna', 'active' => '1']]);
        $layout->method('row')->willReturn(['addEncore' => '1', 'encoreEntries' => $blob]);
        $article = $this->createStub(ArticleModel::class);
        $article->method('__get')->willReturn(3);
        $articleAdapter = $this->createMock(Adapter::class);
        $articleAdapter->expects($pageSpecific && 'content' === $listenerType ? self::exactly(2) : self::never())->method('__call')->with('findById', [7])->willReturn($article);
        $pageAdapter = $this->createMock(Adapter::class);
        $pageAdapter->expects($pageSpecific ? self::exactly(2) : self::never())->method('__call')->with('findWithDetails', [3])->willReturn($page);
        $layoutAdapter = $this->createStub(Adapter::class);
        $layoutAdapter->method('__call')->willReturn($layout);
        $messageAdapter = $this->createMock(TurboInfoAdapter::class);
        $messageAdapter->expects(null === $messageKey ? self::never() : self::once())->method('addInfo')->with($messageKey ?? '');
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($eligible ? self::atLeastOnce() : self::never())->method('getAdapter')->willReturnMap([
            [ArticleModel::class, $articleAdapter], [PageModel::class, $pageAdapter], [LayoutModel::class, $layoutAdapter],
            [StringUtil::class, new Adapter(StringUtil::class)], [Message::class, $messageAdapter],
        ]);
        $schema = $this->createStub(MySQLSchemaManager::class);
        $column = new Column('encoreEntries', Type::getType(Types::BLOB));
        $schema->method('listTableColumns')->willReturn(['encoreentries' => $column, 'addencore' => $column]);
        $connection = $this->createMock(Connection::class);
        $connection->expects($eligible && !$pageSpecific ? self::exactly($active ? 2 : 1) : self::never())->method('createSchemaManager')->willReturn($schema);
        $connection->method('iterateColumn')->willReturnCallback(static function () use ($blob): iterable { yield $blob; });
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(null === $messageKey ? self::never() : self::once())->method('trans')->with($messageKey ?? '', [], 'contao_default')->willReturn($messageKey ?? '');
        $messenger = new TurboHintMessenger(new TurboEntryAvailability($framework, $connection), $framework, $translator);
        $listener = match ($listenerType) {
            'content' => new ContentListener($stack, $framework, $messenger),
            'page' => new PageListener($stack, $framework, $messenger),
            default => new SessionListener($stack, $messenger),
        };
        $listener($dc);
        $listener($dc);
    }

    /** @return iterable<string, array{string, ?string, ?string, string, bool, ?string}> */
    public static function listenerCases(): iterable
    {
        $page = 'qna.backend.turbo_missing_page';
        $global = 'qna.backend.turbo_missing_global';
        yield 'reader' => ['content', 'edit', 'qna_session_reader', 'tl_article', false, $page];
        yield 'list' => ['content', 'edit', 'qna_session_list', 'tl_article', false, $page];
        yield 'reader active' => ['content', 'edit', 'qna_session_reader', 'tl_article', true, null];
        yield 'other parent table' => ['content', 'edit', 'qna_session_reader', 'tl_module', false, $global];
        yield 'text' => ['content', 'edit', 'text', 'tl_article', false, null];
        yield 'content listing' => ['content', '', 'qna_session_reader', 'tl_article', false, null];
        yield 'content no request' => ['content', null, 'qna_session_reader', 'tl_article', false, null];
        yield 'content no record' => ['content', 'edit', null, 'tl_article', false, null];
        yield 'stage' => ['page', 'edit', 'qna_stage', '', false, $page];
        yield 'stage active' => ['page', 'edit', 'qna_stage', '', true, null];
        yield 'regular page' => ['page', 'edit', 'regular', '', false, null];
        yield 'page listing' => ['page', '', 'qna_stage', '', false, null];
        yield 'page no request' => ['page', null, 'qna_stage', '', false, null];
        yield 'page no record' => ['page', 'edit', null, '', false, null];
        yield 'session listing without record' => ['session', '', null, '', false, $global];
        yield 'session edit' => ['session', 'edit', null, '', false, $global];
        yield 'session active' => ['session', '', null, '', true, null];
        yield 'session no request' => ['session', null, null, '', false, null];
    }
}

/** @extends Adapter<Message> */
class TurboInfoAdapter extends Adapter
{
    public function addInfo(string $message): void
    {
    }
}
