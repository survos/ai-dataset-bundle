<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle\Dto;

final readonly class DatasetBatchManifest implements \JsonSerializable
{
    public function __construct(
        public string $dataset,
        public string $datasetKey,
        public string $core,
        public string $task,
        public string $model,
        public string $batchId,
        public string $inputFileId,
        public string $status,
        public int $requestCount,
        public string $batchInputPath,
        public string $createdAt,
        public ?string $outputFileId = null,
        public ?string $errorFileId = null,
        public ?string $rawOutputPath = null,
        public ?string $claimsPath = null,
        public ?string $lastCheckedAt = null,
        public ?string $downloadedAt = null,
        public int $ok = 0,
        public int $failed = 0,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dataset: self::requiredString($data, 'dataset'),
            datasetKey: self::requiredString($data, 'datasetKey'),
            core: self::requiredString($data, 'core'),
            task: self::requiredString($data, 'task'),
            model: self::requiredString($data, 'model'),
            batchId: self::requiredString($data, 'batchId'),
            inputFileId: self::requiredString($data, 'inputFileId'),
            status: self::requiredString($data, 'status'),
            requestCount: self::requiredInt($data, 'requestCount'),
            batchInputPath: self::requiredString($data, 'batchInputPath'),
            createdAt: self::requiredString($data, 'createdAt'),
            outputFileId: self::nullableString($data['outputFileId'] ?? null),
            errorFileId: self::nullableString($data['errorFileId'] ?? null),
            rawOutputPath: self::nullableString($data['rawOutputPath'] ?? null),
            claimsPath: self::nullableString($data['claimsPath'] ?? null),
            lastCheckedAt: self::nullableString($data['lastCheckedAt'] ?? null),
            downloadedAt: self::nullableString($data['downloadedAt'] ?? null),
            ok: self::optionalInt($data, 'ok'),
            failed: self::optionalInt($data, 'failed'),
        );
    }

    public function withStatus(
        string $status,
        ?string $outputFileId,
        ?string $errorFileId,
        ?string $lastCheckedAt = null,
    ): self {
        return new self(
            dataset: $this->dataset,
            datasetKey: $this->datasetKey,
            core: $this->core,
            task: $this->task,
            model: $this->model,
            batchId: $this->batchId,
            inputFileId: $this->inputFileId,
            status: $status,
            requestCount: $this->requestCount,
            batchInputPath: $this->batchInputPath,
            createdAt: $this->createdAt,
            outputFileId: $outputFileId,
            errorFileId: $errorFileId,
            rawOutputPath: $this->rawOutputPath,
            claimsPath: $this->claimsPath,
            lastCheckedAt: $lastCheckedAt ?? date(\DATE_ATOM),
            downloadedAt: $this->downloadedAt,
            ok: $this->ok,
            failed: $this->failed,
        );
    }

    public function withDownloaded(string $rawOutputPath, string $claimsPath, int $ok, int $failed): self
    {
        return new self(
            dataset: $this->dataset,
            datasetKey: $this->datasetKey,
            core: $this->core,
            task: $this->task,
            model: $this->model,
            batchId: $this->batchId,
            inputFileId: $this->inputFileId,
            status: $this->status,
            requestCount: $this->requestCount,
            batchInputPath: $this->batchInputPath,
            createdAt: $this->createdAt,
            outputFileId: $this->outputFileId,
            errorFileId: $this->errorFileId,
            rawOutputPath: $rawOutputPath,
            claimsPath: $claimsPath,
            lastCheckedAt: $this->lastCheckedAt,
            downloadedAt: date(\DATE_ATOM),
            ok: $ok,
            failed: $failed,
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'dataset' => $this->dataset,
            'datasetKey' => $this->datasetKey,
            'core' => $this->core,
            'task' => $this->task,
            'model' => $this->model,
            'batchId' => $this->batchId,
            'inputFileId' => $this->inputFileId,
            'status' => $this->status,
            'requestCount' => $this->requestCount,
            'batchInputPath' => $this->batchInputPath,
            'createdAt' => $this->createdAt,
            'outputFileId' => $this->outputFileId,
            'errorFileId' => $this->errorFileId,
            'rawOutputPath' => $this->rawOutputPath,
            'claimsPath' => $this->claimsPath,
            'lastCheckedAt' => $this->lastCheckedAt,
            'downloadedAt' => $this->downloadedAt,
            'ok' => $this->ok,
            'failed' => $this->failed,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException(sprintf('Manifest is missing required string "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function requiredInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!\is_int($value)) {
            throw new \InvalidArgumentException(sprintf('Manifest is missing required integer "%s".', $key));
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    private static function optionalInt(array $data, string $key): int
    {
        $value = $data[$key] ?? 0;
        if (!\is_int($value)) {
            throw new \InvalidArgumentException(sprintf('Manifest field "%s" must be an integer.', $key));
        }

        return $value;
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Manifest nullable string field received a non-string value.');
        }

        return $value;
    }
}
