<?php

namespace App\Console\Commands;

use App\Http\Resources\ObjectElasticResource;
use App\Models\Article;
use App\Services\ArticleService;
use App\Services\ElasticSearchService;
use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ElasticUpdate extends Command
{

    protected ArticleService $articleService;
    protected ElasticSearchService $elasticSearchService;

    protected $signature = 'app:elastic-update';


    protected $description = 'Command description';

    public function __construct(ArticleService $articleService, ElasticSearchService $elasticSearchService)
    {
        $this->articleService = $articleService;
        $this->elasticSearchService = $elasticSearchService;
        parent::__construct();
    }

//    public function handle()
//    {
//        $this->elasticSearchService->delete('articles', 63583);
//    }
//    public function handle()
//    {
//        $client = app('elasticsearch');
//        $index = config('elasticsearch.prefix') . 'articles';
//
//        $this->info("📊 Elasticsearch va bazadagi id-larni solishtirish boshlandi...\n");
//
//        // 1️⃣ Bazadagi barcha id-larni olamiz
//        $this->info("⏳ Bazadan id-lar olinmoqda...");
//        $dbIds = Article::pluck('id')->map(fn($id) => (int)$id)->toArray();
//        $this->info("✅ Bazadan jami: " . count($dbIds) . " ta id olindi.\n");
//
//        // 2️⃣ Elasticsearchdan barcha id-larni scroll bilan olish
//        $this->info("🔍 Elasticsearch’dan id-lar olinmoqda (scroll orqali)...");
//
//        $elasticIds = [];
//        $scrollId = null;
//
//        $response = $client->search([
//            'index' => $index,
//            '_source' => ['id'],
//            'scroll' => '2m',
//            'size' => 10000,
//            'body' => ['query' => ['match_all' => (object)[]]],
//        ]);
//
//        $total = $response['hits']['total']['value'] ?? 0;
//        $bar = $this->output->createProgressBar($total);
//        $bar->start();
//
//        do {
//            $hits = $response['hits']['hits'] ?? [];
//            if (empty($hits)) break;
//
//            foreach ($hits as $hit) {
//                if (isset($hit['_source']['id'])) {
//                    $elasticIds[] = (int)$hit['_source']['id'];
//                }
//                $bar->advance();
//            }
//
//            $scrollId = $response['_scroll_id'] ?? null;
//
//            if ($scrollId) {
//                $response = $client->scroll([
//                    'scroll_id' => $scrollId,
//                    'scroll' => '2m',
//                ]);
//            } else {
//                break;
//            }
//        } while (!empty($response['hits']['hits']));
//
//        $bar->finish();
//        $this->newLine(2);
//
//        $this->info("✅ Elasticsearch’dan jami: " . count($elasticIds) . " ta id olindi.");
//
//        // 3️⃣ Farqni topamiz
//        $this->info("⚖️ Bazada yo‘q, ammo Elasticsearch’da bor id-larni aniqlayapmiz...");
//        $extraInElastic = collect($elasticIds)->diff($dbIds)->values();
//
//        // 4️⃣ JSON faylga yozamiz
//        $filePath = 'extra_in_elastic.json';
//        Storage::disk('local')->put($filePath, json_encode([
//            'db_count' => count($dbIds),
//            'elastic_count' => count($elasticIds),
//            'extra_in_elastic_count' => $extraInElastic->count(),
//            'extra_in_elastic_ids' => $extraInElastic,
//        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
//
//        $this->newLine(2);
//        if ($extraInElastic->count() > 0) {
//            $this->warn("⚠️ Topildi: " . $extraInElastic->count() . " ta obyekt Elasticsearch’da bor, lekin bazada yo‘q.");
//            $this->info("📁 Natija: storage/app/{$filePath}");
//        } else {
//            $this->info("✅ Elasticsearch va baza id-lari to‘liq mos!");
//        }
//
//        return Command::SUCCESS;
//    }

//    public function handle()
//    {
//        // 1. JSON fayldan farqlarni o‘qiymiz
//        $data = json_decode(Storage::disk('local')->get('status_diff.json'), true);
//
//        $nullElastic = collect($data['differences'])
//            ->whereNotNull('elastic_status')
//            ->pluck('task_id')
//            ->values()
//            ->all();
//
//        if (empty($nullElastic)) {
//            $this->warn("⚠️ JSON faylda elastic_status=null topilmadi.");
//            return Command::SUCCESS;
//        }
//
//
//
//        $this->info("🔄 Elasticsearch’ga qayta yozish boshlandi. Jami: " . count($nullElastic) . " ta obyekt.");
//
//        // 2. Progress bar yaratamiz
//        $bar = $this->output->createProgressBar(count($nullElastic));
//        $bar->start();
//
//        // 3. Har bir obyektni yangilaymiz
//        foreach ($nullElastic as $elastic) {
//            $article = Article::query()->find($elastic);
//            if ($article) {
//                try {
//                    $elasticData = ObjectElasticResource::make($article)->toArray(request());
//                    $this->elasticSearchService->store('articles', $article->id, $elasticData);
//                } catch (\Exception $e) {
//                    $this->warn("\n❌ Obyekt #{$article->id} da xato: " . $e->getMessage());
//                }
//            } else {
//                $this->warn("\n⚠️ Task ID {$elastic} bo‘yicha obyekt topilmadi.");
//            }
//
//            // Progressni yangilaymiz
//            $bar->advance();
//        }
//
//        $bar->finish();
//        $this->newLine(2);
//        $this->info("✅ Barcha obyektlar Elasticsearch’ga qayta yozildi.");
//
//        return Command::SUCCESS;
//    }



    public function handle()
    {
        $client = app('elasticsearch');
        $index  = config('elasticsearch.prefix') . 'articles';

        // 1) Bazadan kerakli maydonlar
        $articles = Article::select('id', 'object_status_id')->get();
        if ($articles->isEmpty()) {
            $this->warn("⚠️ Hech qanday article topilmadi!");
            return Command::SUCCESS;
        }

        $total = $articles->count();
        $this->info("🔍 ID bo‘yicha solishtirish boshlandi. Jami: {$total} ta obyekt.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $differences = [];
        $chunkSize   = 1000; // _mget uchun qulay hajm

        foreach ($articles->chunk($chunkSize) as $chunk) {
            // 2) _mget uchun id-lar
            $ids = $chunk->pluck('id')->map(fn($v) => (string)$v)->values()->all();

            try {
                $resp = $client->mget([
                    'index' => $index,
                    'body'  => ['ids' => $ids],
                    '_source' => ['status.id'] // faqat keraklisini olamiz
                ]);
            } catch (\Throwable $e) {
                $this->error("\n❌ Elasticsearch mget xato: {$e->getMessage()}");
                // bar-ni shu chunk uzunligi bo‘yicha oldinga siljitib yuboramiz
                $bar->advance(count($ids));
                continue;
            }

            // 3) Natijani _id => source xaritaga tushiramiz
            $docs = collect($resp['docs'] ?? [])
                ->keyBy(fn($d) => (int)($d['_id'] ?? 0));

            foreach ($chunk as $pg) {
                $pgId = (int)$pg->id;

                // Enum bo‘lsa ham intga aniq cast qilamiz
                $pgStatus = $pg->object_status_id instanceof \BackedEnum
                    ? (int)$pg->object_status_id->value
                    : (int)$pg->object_status_id;

                $doc = $docs->get($pgId);

                if (empty($doc) || ($doc['found'] ?? false) === false) {
                    // ES’da yo‘q
                    $differences[] = [
                        'id'              => $pgId,
                        'postgres_status' => $pgStatus,
                        'elastic_status'  => null,
                        'error'           => 'Elastic’da topilmadi',
                    ];
                    $bar->advance();
                    continue;
                }

                // ES statusni o‘qiymiz (status object yoki son bo‘lishi ehtimoli)
                $src = $doc['_source'] ?? [];
                $esStatus = null;
                if (isset($src['status'])) {
                    if (is_array($src['status']) && array_key_exists('id', $src['status'])) {
                        $esStatus = $src['status']['id'];
                    } elseif (is_numeric($src['status'])) {
                        $esStatus = (int)$src['status'];
                    }
                }

                if ((int)$esStatus !== $pgStatus) {
                    $differences[] = [
                        'id'              => $pgId,
                        'postgres_status' => $pgStatus,
                        'elastic_status'  => $esStatus,
                        'error'           => 'Status farq qiladi',
                    ];
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        // 4) JSONga yozamiz
        Storage::disk('local')->put('status_diff_by_id.json', json_encode([
            'checked'            => $total,
            'differences_count'  => count($differences),
            'differences'        => $differences,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("✅ Tekshiruv tugadi.");
        $this->info("🟡 Farqlar soni: " . count($differences));
        $this->info("📁 Natija: storage/app/status_diff_by_id.json");

        return \Illuminate\Console\Command::SUCCESS;
    }


}
