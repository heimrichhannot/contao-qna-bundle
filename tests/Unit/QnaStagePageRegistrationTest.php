<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Unit;

use Contao\CoreBundle\DependencyInjection\Attribute\AsPage;
use Contao\CoreBundle\Routing\Page\PageRoute;
use Contao\PageModel;
use HeimrichHannot\QnaBundle\Controller\Page\QnaStageController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

final class QnaStagePageRegistrationTest extends TestCase
{
    public function testStagePageUsesItsOwnOptionalAliasParameter(): void
    {
        $attribute = (new \ReflectionClass(QnaStageController::class))
            ->getAttributes(AsPage::class)[0]
            ->newInstance();

        self::assertSame('qna_stage', $attribute->type);
        self::assertSame('{alias}', $attribute->path);
        self::assertSame(['alias' => ''], $attribute->defaults);
        self::assertTrue($attribute->contentComposition);
        self::assertStringNotContainsString('auto_item', (string) $attribute->path);
    }

    public function testOptionalAliasWorksBeforeTheRootUrlSuffix(): void
    {
        $page = new RouteTestPageModel();
        $page->id = 17;
        $page->alias = 'buehne';
        $page->rootLanguage = 'de';
        $page->domain = '';
        $page->rootUseSSL = false;
        $page->urlPrefix = '';
        $page->urlSuffix = '.html';

        $route = new PageRoute($page, '{alias}', ['alias' => '']);
        $routes = new RouteCollection();
        $routes->add('stage', $route);
        $context = new RequestContext();
        $matcher = new UrlMatcher($routes, $context);
        $generator = new UrlGenerator($routes, $context);

        self::assertSame('', $matcher->match('/buehne.html')['alias']);
        self::assertSame('mobilitaet-der-zukunft', $matcher->match('/buehne/mobilitaet-der-zukunft.html')['alias']);
        self::assertSame(
            '/buehne/mobilitaet-der-zukunft.html',
            $generator->generate('stage', ['alias' => 'mobilitaet-der-zukunft']),
        );

        $this->expectException(InvalidParameterException::class);
        $generator->generate('stage', ['alias' => '']);
    }

    public function testStagePaletteAndTranslationsAreBundleLocal(): void
    {
        $GLOBALS['TL_DCA'] = ['tl_page' => ['palettes' => []]];
        require \dirname(__DIR__, 2).'/contao/dca/tl_page.php';

        $dca = $GLOBALS['TL_DCA'] ?? null;
        self::assertIsArray($dca);
        self::assertIsArray($dca['tl_page'] ?? null);
        self::assertIsArray($dca['tl_page']['palettes'] ?? null);
        $palette = $dca['tl_page']['palettes']['qna_stage'] ?? null;
        $german = require \dirname(__DIR__, 2).'/translations/contao_default.de.php';
        $english = require \dirname(__DIR__, 2).'/translations/contao_default.en.php';

        self::assertIsString($palette);
        self::assertIsArray($german);
        self::assertIsArray($english);
        self::assertStringContainsString('{protected_legend:hide},protected', $palette);
        self::assertStringContainsString('{layout_legend:hide},includeLayout', $palette);
        self::assertArrayHasKey('PTY.qna_stage.0', $german);
        self::assertArrayHasKey('PTY.qna_stage.1', $german);
        self::assertArrayHasKey('PTY.qna_stage.0', $english);
        self::assertArrayHasKey('PTY.qna_stage.1', $english);
    }
}

final class RouteTestPageModel extends PageModel
{
    public int $id = 0;

    public string $alias = '';

    public string $rootLanguage = '';

    public string $domain = '';

    public bool $rootUseSSL = false;

    public string $urlPrefix = '';

    public string $urlSuffix = '';

    public function __construct()
    {
    }

    public function loadDetails(): PageModel
    {
        return $this;
    }
}
