<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use HeimrichHannot\QnaBundle\HeimrichHannotQnaBundle;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollection;

final class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(HeimrichHannotQnaBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class]),
        ];
    }

    public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel): ?RouteCollection
    {
        $path = __DIR__.'/../../config/routes.yaml';
        $loader = $resolver->resolve($path);

        if (false === $loader) {
            return null;
        }

        $routes = $loader->load($path);

        if (!$routes instanceof RouteCollection) {
            throw new \UnexpectedValueException('The Q&A route loader did not return a route collection.');
        }

        return $routes;
    }
}
