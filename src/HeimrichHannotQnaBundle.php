<?php

declare(strict_types=1);

namespace HeimrichHannot\QnaBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class HeimrichHannotQnaBundle extends AbstractBundle
{
    protected string $extensionAlias = 'contao_qna';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->integerNode('polling_interval')->min(1)->defaultValue(2500)->end()
                ->integerNode('max_question_length')->min(1)->defaultValue(500)->end()
                ->integerNode('question_cooldown')->min(0)->defaultValue(20)->end()
                ->integerNode('idle_polling_interval_multiplier')->min(1)->defaultValue(4)->end()
                ->integerNode('max_polling_interval_multiplier')->min(1)->defaultValue(16)->end()
            ->end()
        ;
    }

    /**
     * @param array{
     *     polling_interval: int,
     *     max_question_length: int,
     *     question_cooldown: int,
     *     idle_polling_interval_multiplier: int,
     *     max_polling_interval_multiplier: int,
     * } $config
     */
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        $container->parameters()
            ->set('contao_qna.polling_interval', $config['polling_interval'])
            ->set('contao_qna.max_question_length', $config['max_question_length'])
            ->set('contao_qna.question_cooldown', $config['question_cooldown'])
            ->set('contao_qna.idle_polling_interval_multiplier', $config['idle_polling_interval_multiplier'])
            ->set('contao_qna.max_polling_interval_multiplier', $config['max_polling_interval_multiplier'])
        ;

        $container->import(\dirname(__DIR__).'/config/services.yaml');
    }
}
