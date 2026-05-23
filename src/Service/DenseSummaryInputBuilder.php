<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle\Service;

use Survos\AiDatasetBundle\Dto\DenseSummaryInput;
use Survos\DataContracts\Dto\Item\BaseItemDto;
use Survos\DataContracts\Metadata\ContentType;
use Survos\DataContracts\Vocabulary\ItemField;
use Survos\DataContracts\Vocabulary\MuseumVocab;
use Survos\FieldBundle\Service\FieldReader;
use Twig\Environment;
use Twig\Error\LoaderError;

final class DenseSummaryInputBuilder
{
    private const BASE_TEMPLATE = '@SurvosAiDataset/dense_summary/base.txt.twig';

    /**
     * Canonical normalized row fields that should be useful to dense summaries.
     *
     * @var list<string>
     */
    private const CANONICAL_FIELDS = [
        ItemField::TITLE,
        ItemField::DESCRIPTION,
        ItemField::PHYSICAL_DESCRIPTION,
        ItemField::CONTEXT_DESCRIPTION,
        ItemField::DATE,
        ItemField::CREATOR,
        ItemField::TYPE,
        ItemField::GENRE_SPECIFIC,
        ItemField::GENRE_BASIC,
        ItemField::KEYWORDS,
        ItemField::CITATION,
        ItemField::CITATION_URL,
        ItemField::RIGHTS,
        ItemField::LANGUAGE,
        ItemField::COUNTRY,
        ItemField::CITY,
        MuseumVocab::CULTURE,
        MuseumVocab::MEDIUM,
        MuseumVocab::MATERIAL,
        MuseumVocab::TECHNIQUE,
        MuseumVocab::PLACE,
        MuseumVocab::PERSON,
        MuseumVocab::SUBJECT,
        MuseumVocab::ORGANISATION,
        MuseumVocab::PERIOD,
        MuseumVocab::COLLECTION,
        MuseumVocab::DEPARTMENT,
    ];

    public function __construct(
        private readonly Environment $twig,
        private readonly FieldReader $fieldReader,
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public function build(string $dataset, string $core, array $row): DenseSummaryInput
    {
        $id = $this->requiredRowId($row);
        $contentType = ContentType::fromRecord($row);
        $dtoClass = ContentType::dtoClass($contentType);
        $dto = $this->hydrateDto($dtoClass, $row);
        $fields = $this->projectFields($dto, $row, $dtoClass);
        $template = $this->templateFor($contentType);

        $context = [
            'dataset' => $dataset,
            'core' => $core,
            'id' => $id,
            'contentType' => $contentType,
            'dtoClass' => $dtoClass,
            'label' => $dto->label(),
            'fields' => $fields,
        ];

        return new DenseSummaryInput(
            id: $id,
            dataset: $dataset,
            core: $core,
            contentType: $contentType,
            dtoClass: $dtoClass,
            template: $template,
            context: $context,
            systemPrompt: $this->systemPrompt(),
            userPrompt: trim($this->twig->render($template, $context)),
        );
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Write a dense museum-chat summary from professionally catalogued metadata only.
Do not extract fields, classify, speculate, or add facts.
Return JSON only: {"dense_summary":"..."}.
PROMPT;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function requiredRowId(array $row): string
    {
        $value = $row[ItemField::ID] ?? $row[ItemField::SOURCE_ID] ?? null;
        if (!\is_scalar($value) || trim((string) $value) === '') {
            throw new \RuntimeException(sprintf('Normalized row is missing "%s" or "%s".', ItemField::ID, ItemField::SOURCE_ID));
        }

        return trim((string) $value);
    }

    /**
     * @param class-string $dtoClass
     * @param array<string,mixed> $row
     */
    private function hydrateDto(string $dtoClass, array $row): BaseItemDto
    {
        if (!is_a($dtoClass, BaseItemDto::class, true)) {
            throw new \RuntimeException(sprintf('%s is not a %s subclass.', $dtoClass, BaseItemDto::class));
        }

        return $dtoClass::fromNormalized($row);
    }

    /**
     * @param array<string,mixed> $row
     * @param class-string $dtoClass
     * @return list<array{name:string,label:string,value:mixed,source:string}>
     */
    private function projectFields(BaseItemDto $dto, array $row, string $dtoClass): array
    {
        $projected = [];
        $seen = [];

        foreach ($this->fieldNamesForDto($dtoClass) as $property) {
            if (!property_exists($dto, $property)) {
                continue;
            }
            $value = $dto->$property;
            if ($this->isEmptyValue($value)) {
                continue;
            }
            $projected[] = [
                'name' => $property,
                'label' => $this->fieldLabel($dtoClass, $property),
                'value' => $value,
                'source' => 'dto',
            ];
            $seen[$property] = true;
        }

        foreach (self::CANONICAL_FIELDS as $field) {
            if (isset($seen[$field]) || !array_key_exists($field, $row) || $this->isEmptyValue($row[$field])) {
                continue;
            }
            $projected[] = [
                'name' => $field,
                'label' => $field,
                'value' => $row[$field],
                'source' => 'normalized',
            ];
        }

        return $projected;
    }

    /**
     * @param class-string $dtoClass
     * @return list<string>
     */
    private function fieldNamesForDto(string $dtoClass): array
    {
        $descriptors = $this->fieldReader->getDescriptors($dtoClass);
        if ($descriptors !== []) {
            return array_map(static fn ($descriptor): string => $descriptor->name, $descriptors);
        }

        $dto = new $dtoClass();
        return array_values(array_filter(
            array_keys(get_object_vars($dto)),
            static fn (string $property): bool => !\in_array($property, ['unmapped', 'denseSummary'], true),
        ));
    }

    /**
     * @param class-string $dtoClass
     */
    private function fieldLabel(string $dtoClass, string $property): string
    {
        $descriptor = $this->fieldReader->getDescriptor($dtoClass, $property);

        return $descriptor?->getFallbackLabel() ?? $property;
    }

    private function templateFor(string $contentType): string
    {
        $template = sprintf('@SurvosAiDataset/dense_summary/%s.txt.twig', $contentType);
        try {
            $this->twig->load($template);

            return $template;
        } catch (LoaderError) {
            return self::BASE_TEMPLATE;
        }
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
