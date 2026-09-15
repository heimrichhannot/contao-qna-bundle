<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\Tests\Fixtures;

use Contao\CoreBundle\String\HtmlAttributes;
use Contao\CoreBundle\Twig\Extension\ContaoExtension;
use Contao\CoreBundle\Twig\ResponseContext\AddTokenParser;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TemplateEnvironment
{
    public static function create(): Environment
    {
        $files = new FilesystemLoader();
        $files->addPath(\dirname(__DIR__, 2).'/contao/templates', 'Contao');
        // Only the unrelated Contao page wrapper is substituted; bundle templates/includes are real.
        $twig = new Environment(new ChainLoader([new ArrayLoader([
            '@Contao/content_element/_base.html.twig' => '<section>{% block content %}{% endblock %}</section>',
        ]), $files]), ['strict_variables' => true, 'autoescape' => 'html']);
        $twig->addTokenParser(new AddTokenParser(ContaoExtension::class));
        // HtmlAttributes escapes its own output; without this it would be escaped twice.
        // ContaoExtension does the same (see its constructor, addSafeClass call).
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(HtmlAttributes::class, ['html']);
        // Mirrors ContaoExtension::getFunctions(); the real one needs the full Contao container.
        $twig->addFunction(new TwigFunction('attrs', self::attrs(...)));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => '/'.$path));
        $twig->addFunction(new TwigFunction('request_token', static fn (): string => 'secret-token'));
        $twig->addFilter(new TwigFilter('trans', self::translate(...)));

        return $twig;
    }

    /**
     * @param HtmlAttributes|iterable<string, \Stringable|bool|float|int|string|null>|string|null $attributes
     */
    private static function attrs(HtmlAttributes|iterable|string|null $attributes = null): HtmlAttributes
    {
        return new HtmlAttributes($attributes);
    }

    /** @param array<scalar|null> $parameters */
    private static function translate(string $key, array $parameters = []): string
    {
        /** @var array<string, string> $translations */
        $translations = require \dirname(__DIR__, 2).'/translations/contao_default.en.php';

        return vsprintf($translations[$key] ?? $key, $parameters);
    }
}
