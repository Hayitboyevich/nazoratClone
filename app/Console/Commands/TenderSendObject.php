<?php

namespace App\Console\Commands;

use App\Enums\ObjectStatusEnum;
use App\Enums\ObjectTypeEnum;
use App\Models\Article;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\error;

class TenderSendObject extends Command
{

    protected $signature = 'app:tender-send';


    protected $description = 'Command description';


    public function handle()
    {
        $client = new Client();
        $apiCredentials = config('app.tender.login') . ':' . config('app.tender.password');
        $headers = [
            'Authorization' => 'Basic ' . base64_encode($apiCredentials),
        ];
        $articles = Article::query()
            ->with([
                'organization',
                'vendor',
            ])
            ->whereNotNull('gnk_id')
            ->where('object_status_id', ObjectStatusEnum::SUBMITTED)
            ->whereIn('funding_source_id', [1,3])
//            ->where('id', 62233)
            ->where('sent_to_tender', 0)
            ->limit(60)
            ->get();
        foreach ($articles as $article) {
            $data = [
                "gnk_id" => intval($article->gnk_id),
                "obyekt_nomi" => $article->name,
                'customer_name' => optional($article->organization->first())->organization_name,
                'customer_inn' => optional($article->organization->first())->pinfl,
                'pudratchi_name' => optional($article->vendor->first())->organization_name,
                'pudratchi_inn' => optional($article->vendor->first())->pinfl,
                "task_id" => $article->task_id ?? $article->manual_task_id,
                "dalolatnoma_file_link" => $article->closed_file,
                "tasdiqlangan_sana" => $article->closed_at,
                "created_date" => $article->created_at->format('Y-m-d H:i:s'),
            ];

            try {
                $response = $client->post(config('app.tender.push_url'), [
                    'headers' => $headers,
                    'json' => $data,
                    'timeout' => 10,
                    'verify' => false
                ]);
                $article->update(['sent_to_tender' => 1]);
                $result = json_decode($response->getBody()->getContents(), true);
                Log::channel('daily')->log('info', 'TenderSendObject: Sending article ID ' . $article->id, [$result]);

                $json = htmlspecialchars(
                    json_encode(
                        $result,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
                );

            } catch (\Exception $exception) {
                $article->update(['sent_to_tender' => 2]);
                $json = htmlspecialchars(
                    json_encode(
                        $exception->getMessage(),
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                    )
                );
                Log::channel('daily')->log('error', $exception->getMessage(), ['object_id' => $article->id]);
            }
            $text = $this->generateText($article, $json);
            Http::post("https://api.telegram.org/bot".config('app.telegram.token')."/sendMessage", [
                "chat_id" => config('app.telegram.chat_id_tender'),
                "text" => $text,
                "parse_mode" => "HTML"
            ]);
        }
    }

    public function generateText($article, $json)
    {
        $text = "
🚀 <b>TENDER PUSH INFO</b>

━━━━━━━━━━━━━━━━━━

🆔 Article ID :
<code>{$article->id}</code>

📌 GNK ID :
<code>{$article->gnk_id}</code>

📄 Article Name :
<code>{$article->name}</code>

📝 Dalolatnoma linki :
<a href='{$article->closed_file}'>📎 Open File</a>

📅 Dalolatnoma tasdiqlangan sana :
<code>{$article->closed_at}</code>

📊 Article Status :
<code>{$article->object_status_id->value}</code>

━━━━━━━━━━━━━━━━━━

📥 RESPONSE:
<pre>{$json}</pre>
";

        return $text;
    }
}
