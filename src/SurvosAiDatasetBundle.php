<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle;

use Survos\AiDatasetBundle\Service\DatasetAiService;
use Survos\AiDatasetBundle\Service\DenseSummaryInputBuilder;
use Survos\ClaimsBundle\SurvosClaimsBundle;
use Survos\\DatasetBundle\\SurvosDatasetBundle;
use Survos\FieldBundle\SurvosFieldBundle;
use Survos\JsonlBundle\SurvosJsonlBundle;
use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tacman\AiBatch\TacmanAiBatchBundle;

#[RequiredBundle(SurvosKitBundle::class)]
#[RequiredBundle(SurvosDatasetBundle::class)]
#[RequiredBundle(SurvosJsonlBundle::class)]
#[RequiredBundle(SurvosFieldBundle::class)]
#[RequiredBundle(SurvosClaimsBundle::class)]
#[RequiredBundle(TacmanAiBatchBundle::class)]
final class SurvosAiDatasetBundle extends AbstractSurvosBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        $container->services()
            ->defaults()
                ->autowire()
                ->autoconfigure()
            ->set(DatasetAiService::class)
                ->public()
            ->set(DenseSummaryInputBuilder::class)
                ->public();
    }
}
