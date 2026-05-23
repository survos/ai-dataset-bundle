<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle\Dto;

final readonly class DenseSummaryInput
{
    /**
     * @param array<string,mixed> $context
     */
    public function __construct(
        public string $id,
        public string $dataset,
        public string $core,
        public string $contentType,
        public string $dtoClass,
        public string $template,
        public array $context,
        public string $systemPrompt,
        public string $userPrompt,
    ) {
    }
}
