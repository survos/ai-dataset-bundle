<?php

declare(strict_types=1);

namespace Survos\AiDatasetBundle\Dto;

final readonly class DenseSummaryResult implements \JsonSerializable
{
    public function __construct(
        public string $denseSummary,
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $summary = $data['dense_summary'] ?? null;
        if (!\is_string($summary) || '' === trim($summary)) {
            throw new \InvalidArgumentException('Dense summary result is missing "dense_summary".');
        }

        return new self(denseSummary: trim($summary));
    }

    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'dense_summary' => $this->denseSummary,
        ];
    }
}
