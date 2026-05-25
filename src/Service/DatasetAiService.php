<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle\Service;

use Survos\AiDatasetBundle\Dto\DenseSummaryResult;
use Survos\ClaimsBundle\Entity\Claim;
use Survos\DatasetBundle\Service\DataPaths;
use Survos\DataContracts\Vocabulary\ItemField;
use Survos\JsonlBundle\IO\JsonlReader;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DatasetAiService
{
    public function __construct(
        private readonly DataPaths $dataPaths,
        private readonly DenseSummaryInputBuilder $denseSummaryInputBuilder,
        private readonly PlatformInterface $platform,
    ) {
    }

    #[AsCommand('ai:dataset:prompt', 'preview the dense-summary prompt for one normalized dataset row')]
    public function prompt(
        SymfonyStyle $io,
        #[Argument('dataset key, e.g. mus/aust')] string $dataset,
        #[Option('core JSONL filename stem')] string $core = 'obj',
        #[Option('record id to preview')] ?string $id = null,
        #[Option('1-based JSONL line number to preview when --id is omitted')] int $line = 1,
    ): int {
        if ($line < 1) {
            throw new \InvalidArgumentException('--line must be >= 1.');
        }

        $path = $this->normalizedPath($dataset, $core);
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('Readable normalized file not found: %s', $path));
        }

        $row = $this->findRow($path, $id, $line);
        if ($row === null) {
            $io->error($id === null
                ? sprintf('No row found at line %d in %s.', $line, $path)
                : sprintf('No row found with id "%s" in %s.', $id, $path));

            return Command::FAILURE;
        }

        $input = $this->denseSummaryInputBuilder->build($dataset, $core, $row);

        $io->table(
            ['Field', 'Value'],
            [
                ['Dataset', $input->dataset],
                ['Core', $input->core],
                ['Record ID', $input->id],
                ['Content type', $input->contentType],
                ['DTO class', $input->dtoClass],
                ['Template', $input->template],
                ['Projected fields', (string) \count($input->context['fields'])],
            ],
        );

        $io->section('System Prompt');
        $io->writeln($input->systemPrompt);

        $io->section('User Prompt');
        $io->writeln($input->userPrompt);

        return Command::SUCCESS;
    }

    #[AsCommand('ai:dataset:run', 'generate a dense summary for one normalized dataset row')]
    public function run(
        SymfonyStyle $io,
        #[Argument('dataset key, e.g. mus/aust')] string $dataset,
        #[Option('core JSONL filename stem')] string $core = 'obj',
        #[Option('record id to process')] ?string $id = null,
        #[Option('1-based JSONL line number to process when --id is omitted')] int $line = 1,
        #[Option('model to use')] string $model = 'gpt-5-mini',
        #[Option('prepare for batch mode instead of direct call')] bool $batch = false,
    ): int {
        if ($batch) {
            $io->warning('Batch mode will be added after the prompt/result contract is validated. Use direct mode for now.');
            return Command::INVALID;
        }

        $path = $this->normalizedPath($dataset, $core);
        if (!is_file($path) || !is_readable($path)) {
            throw new \RuntimeException(sprintf('Readable normalized file not found: %s', $path));
        }

        $row = $this->findRow($path, $id, $line);
        if ($row === null) {
            $io->error($id === null
                ? sprintf('No row found at line %d in %s.', $line, $path)
                : sprintf('No row found with id "%s" in %s.', $id, $path));

            return Command::FAILURE;
        }

        $input = $this->denseSummaryInputBuilder->build($dataset, $core, $row);
        $response = $this->platform->invoke(
            $model,
            new MessageBag(
                Message::forSystem($input->systemPrompt),
                Message::ofUser($input->userPrompt),
            ),
            ['response_format' => DenseSummaryResult::class],
        );

        $object = $response->asObject();
        $result = $object instanceof DenseSummaryResult
            ? $object
            : DenseSummaryResult::fromArray($this->normalizeResponse($object));

        $io->table(
            ['Field', 'Value'],
            [
                ['Dataset', $input->dataset],
                ['Core', $input->core],
                ['Record ID', $input->id],
                ['Content type', $input->contentType],
                ['DTO class', $input->dtoClass],
                ['Model', $model],
            ],
        );

        $io->section('Dense Summary');
        $io->writeln($result->denseSummary);

        $io->section('Claim');
        $io->writeln(json_encode([
            'id' => $input->id,
            'dataset' => $dataset,
            'core' => $core,
            'task' => 'dense_summary',
            'claims' => [[
                'predicate' => Claim::PRED_DENSE_SUMMARY,
                'value' => $result->denseSummary,
                'confidence' => 100,
                'basis' => 'AI dense summary generated from normalized professional catalogue metadata.',
            ]],
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    private function normalizedPath(string $dataset, string $core): string
    {
        return $this->dataPaths->stageDir($dataset, 'normalize') . '/' . $core . '.jsonl';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findRow(string $path, ?string $id, int $line): ?array
    {
        foreach (JsonlReader::open($path) as $rowNumber => $row) {
            if ($id === null) {
                if ($rowNumber === $line) {
                    return $row;
                }
                continue;
            }

            $rowId = $row[ItemField::ID] ?? $row[ItemField::SOURCE_ID] ?? null;
            if (\is_scalar($rowId) && (string) $rowId === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeResponse(mixed $content): array
    {
        if ($content instanceof \JsonSerializable) {
            $content = $content->jsonSerialize();
        }

        if (\is_array($content)) {
            return $content;
        }

        $text = trim((string) $content);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```\s*$/', '', $text) ?? $text;
        $decoded = json_decode($text, true);

        return \is_array($decoded) ? $decoded : ['dense_summary' => $text];
    }
}
