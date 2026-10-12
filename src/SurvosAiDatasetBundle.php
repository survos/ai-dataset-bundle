<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle;

use Survos\AiDatasetBundle\Service\DatasetAiService;
use Survos\AiDatasetBundle\Service\DenseSummaryInputBuilder;
use Survos\ClaimsBundle\SurvosClaimsBundle;
use Survos\DataContracts\Path\DataPaths;
use Survos\FieldBundle\SurvosFieldBundle;
use Survos\JsonlBundle\SurvosJsonlBundle;
use Survos\Kit\AbstractSurvosBundle;
use Survos\Kit\SurvosKitBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tacman\AiBatch\TacmanAiBatchBundle;

#[RequiredBundle(SurvosKitBundle::class)]
#[RequiredBundle(SurvosJsonlBundle::class)]
#[RequiredBundle(SurvosFieldBundle::class)]
#[RequiredBundle(SurvosClaimsBundle::class)]
#[RequiredBundle(TacmanAiBatchBundle::class)]
// Symfony\Component\HttpKernel\Bundle\Bundle <-- Flex auto-registration marker (see Survos\Kit\AbstractSurvosBundle)
final class SurvosAiDatasetBundle extends AbstractSurvosBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        parent::loadExtension($config, $container, $builder);

        // DatasetAiService only needs DataPaths (survos/data-contracts, a plain library) to find
        // normalized rows under APP_DATA_DIR. Where dataset-bundle is installed it registers
        // DataPaths from its own richer path config; otherwise define it from the env var.
        $bundles = $builder->hasParameter('kernel.bundles') ? (array) $builder->getParameter('kernel.bundles') : [];
        if (!isset($bundles['SurvosDatasetBundle'])) {
            $container->services()->set(DataPaths::class)
                ->public()
                ->args(['$dataDir' => '%env(APP_DATA_DIR)%']);
        }

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
