# survos/ai-dataset-bundle

Dataset-scale AI batch processing for Survos/Museado datasets.

This bundle is intentionally separate from `survos/ai-workflow-bundle`.
`ai-workflow-bundle` operates on individual workflow subjects. This bundle
operates on dataset JSONL stages, writes durable batch artifacts, and uses the
canonical workspace paths from `survos/data-contracts` (`DataPaths`, rooted at
`APP_DATA_DIR`).

## Responsibilities

- Read normalized rows from `20_normalize/{core}.jsonl`.
- Write provider-ready batch input JSONL and manifests to `40_ai/`.
- Submit/check/download OpenAI batch jobs through `survos/ai-batch-bundle`.
- Convert downloaded batch responses into portable claim JSONL files.
- Leave later enrichment/import stages to consume those claim files.

## Commands

Commands are exposed as methods on `Survos\AiDatasetBundle\Service\DatasetAiService`.

```bash
php bin/console ai:dataset:estimate mus/aust --core=obj
php bin/console ai:dataset:prepare mus/aust --core=obj --force
php bin/console ai:dataset:submit mus/aust --core=obj --force
php bin/console ai:dataset:status mus/aust
php bin/console ai:dataset:download mus/aust --core=obj --force
```

`ai:dataset:submit` is the paid provider call. `estimate` and `prepare` are local.

## Files

For dataset `mus/aust` and core `obj`, the bundle uses:

| Path | Purpose |
|---|---|
| `20_normalize/obj.jsonl` | Normalized source records |
| `40_ai/obj.dense_summary.batch.input.jsonl` | OpenAI batch input |
| `40_ai/obj.dense_summary.batch.json` | Local batch manifest |
| `40_ai/obj.dense_summary.batch.output.jsonl` | Raw OpenAI batch output |
| `40_ai/obj.jsonl` | Portable claim rows for enrichment |

All paths are resolved with `Survos\DataBundle\Service\DataPaths`.

## Install

```bash
composer require survos/ai-dataset-bundle
```

Register the bundle:

```php
Survos\AiDatasetBundle\SurvosAiDatasetBundle::class => ['all' => true],
```

Required runtime bundles:

- `survos/jsonl-bundle`
- `survos/ai-batch-bundle`
- `survos/claims-bundle`

Optional:

- `yethee/tiktoken` for better token estimates.
