<?php

namespace App\Services;

use App\Http\Resources\ObjectElasticResource;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ElasticSearchService
{
    protected $client;

    public function __construct()
    {
        $this->client = App::make('elasticsearch');
    }

    public function client()
    {
        return $this->client;
    }

    public function syncWithProgress(Model $model, ?string $indexName, int $chunkSize, Command $command)
    {
        $table = $model->getTable();
        $index = $indexName ?? Str::snake($table);

        if (!$this->indexExists($index)) {
            $command->info("🧱 Index is not exists, creating...");
            $this->createIndexFromModel($model, $index);
            $command->info("✅ Index '{$index}' created.");
        } else {
            $command->info("ℹ️ Index '{$index}' already exists, skipped this step.");
        }

        $total = $model->count();
        $command->info("🔢 Total items: {$total}");
        $progressBar = $command->getOutput()->createProgressBar($total);
        $progressBar->start();

        $processed = 0;
        $model->newQuery()->chunk($chunkSize, function ($rows) use ($index, &$processed, $total, $progressBar) {

            $bulk = ['body' => []];

            foreach ($rows as $row) {
                $data = ObjectElasticResource::make($row)->resolve();
//                $data = $row->toArray();

                $bulk['body'][] = [
                    'index' => [
                        '_index' => config('elasticsearch.prefix') . $index,
                        '_id' => $row->getKey(),
                    ],
                ];

                $bulk['body'][] = $data;
            }

            // bulk upload to Elasticsearch
           $response =  $this->client->bulk($bulk);

            Log::info('Bulk response', $response->asArray());

            $processed += count($rows);
            $progressBar->advance(count($rows));
        });

        $progressBar->finish();
    }

    public function decodeCompressed($value, $asArray = false)
    {
        if (empty($value)) {
            return null;
        }

        // 1. Base64 decode
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return null;
        }

        // 2. GZ uncompress (ba’zida gzdecode ishlaydi)
        $uncompressed = gzuncompress($decoded);
        if ($uncompressed === false) {
            $uncompressed = gzdecode($decoded);
        }

        if ($uncompressed === false || empty($uncompressed)) {
            return null;
        }

//        // 3. Agar JSON bo‘lsa, parse qilamiz
//        $json = json_decode($uncompressed, $asArray);
//        if (json_last_error() === JSON_ERROR_NONE) {
//            return $json;
//        }

        // Aks holda oddiy string sifatida qaytaramiz
        return $uncompressed;
    }

    protected function getTableColumns(Model $model): array
    {
        $table = $model->getTable();
        $connection = $model->getConnectionName() ?? config('database.default');
        $driver = config("database.connections.$connection.driver");

        if ($driver === 'pgsql') {
            $columns = DB::select("
            SELECT column_name, data_type
            FROM information_schema.columns
            WHERE table_name = ?
        ", [$table]);
        } else {
            // MySQL uchun
            $columns = DB::select("SHOW COLUMNS FROM {$table}");
            $columns = array_map(function ($col) {
                return (object)[
                    'column_name' => $col->Field,
                    'data_type' => $col->Type,
                ];
            }, $columns);
        }


        $mappings = ['properties' => []];
        foreach ($columns as $col) {
            $type = $this->mapTypeByDb($col->data_type);
            $mappings['properties'][$col->column_name] = ['type' => $type];
        }

        return $mappings;
    }

    protected function mapTypeByDb(string $dbType): string
    {
        $dbType = strtolower($dbType);

        return match (true) {
            str_contains($dbType, 'int') => 'integer',
            str_contains($dbType, 'bool') => 'boolean',
            str_contains($dbType, 'float'),
            str_contains($dbType, 'double'),
            str_contains($dbType, 'numeric'),
            str_contains($dbType, 'decimal') => 'float',
            str_contains($dbType, 'date'),
            str_contains($dbType, 'time') => 'date',
            str_contains($dbType, 'json') => 'object',
            str_contains($dbType, 'jsonb') => 'object',
            default => 'text',
        };
    }

    /**
     * Index mavjudligini tekshiradi
     */
    protected function indexExists(string $index): bool
    {
        return $this->client->indices()->exists(['index' => config('elasticsearch.prefix') . $index])->asBool();
    }

    protected function createIndexFromModel(Model $model, string $index)
    {
        $mappings = $this->getTableColumns($model);

        $specialKeywordFields = [
            'gnk_id',
            'stir',
            'pinfl',
            'number_protocol',
            'reestr_number',
            'cadastral_number',
            'task_id',
            'manual_task_id',
        ];

        foreach ($specialKeywordFields as $field) {
            if (isset($mappings['properties'][$field])) {
                $mappings['properties'][$field] = ['type' => 'keyword'];
            }
        }

        foreach ($mappings['properties'] as $name => &$property) {
            if ($property['type'] === 'date') {
                $property['format'] = 'yyyy-MM-dd HH:mm:ss||strict_date_optional_time||epoch_millis';
            }
        }

        $this->client->indices()->create([
            'index' => config('elasticsearch.prefix') . $index,
            'body' => ['mappings' => $mappings],
        ]);
    }

    public function info()
    {
        return $this->client->info();
    }

    public function store($index, $id, $data)
    {
        return $this->client->index([
            'index' => config('elasticsearch.prefix') . $index,
            'id' => $id,
            'body' => $data,
        ]);
    }

    public function search($index, $query)
    {
        return $this->client->search([
            'index' => config('elasticsearch.prefix') . $index,
            'body' => [
                'query' => [
                    'match' => $query,
                ],
            ],
        ]);
    }

    public function delete($index, $id)
    {
        return $this->client->delete([
            'index' => config('elasticsearch.prefix') . $index,
            'id' => $id,
        ]);
    }
}
