<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Article;
use App\Services\ElasticSearchService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Throwable;

class PushArticleToElastic extends Command
{

    protected $signature = 'es:sync {model : The model class name, e.g. User or App\\Models\\User} {--index=} {--chunk=1000}';
    protected $description = 'Sync a Laravel model data to Elasticsearch index';
    protected ElasticSearchService $service;

    public function __construct(ElasticSearchService $service)
    {
        parent::__construct();
        $this->service = $service;
    }

    public function handle()
    {
        ini_set('memory_limit', '-1');

        $modelArg = $this->argument('model');
        $index = $this->option('index');
        $chunk = (int)$this->option('chunk');

        if (!Str::startsWith($modelArg, 'App\\Models\\')) {
            $modelClass = "App\\Models\\{$modelArg}";
        } else {
            $modelClass = $modelArg;
        }

        if (!class_exists($modelClass)) {
            $this->error("❌ Model topilmadi: {$modelClass}");
            return 1;
        }

        $model = new $modelClass();

        $this->info("🔍 Syncing model: {$modelClass}");
        $this->info("📦 Table: {$model->getTable()} | Index: " . ($index ?? $model->getTable()));
        $this->info("Chunk size: {$chunk}");

        $start = microtime(true);


        $this->service->syncWithProgress($model, $index, $chunk, $this);

        $duration = round(microtime(true) - $start, 2);
        $this->newLine();
        $this->info("✅ Sync completed in {$duration} seconds.");

        return 0;
    }

}
