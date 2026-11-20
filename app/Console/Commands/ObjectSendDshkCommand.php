<?php

namespace App\Console\Commands;

use App\Enums\ObjectStatusEnum;
use App\Models\Article;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ObjectSendDshkCommand extends Command
{

    protected $signature = 'app:object-dshk';


    protected $description = 'Command description';


    public function handle()
    {
        $articles = Article::query()
            ->whereNull('send_dshk')
            ->limit(30)
            ->get();

        foreach ($articles as $article) {
           $response = Http::withBasicAuth(
               'dshk-user',
               'DK(*Unhy22026@'
           )
               ->timeout(10)
               ->connectTimeout(5)
               ->get('https://api-dshk.shaffofqurilish.uz/api/get-push/'.$article->id);
           if ($response->ok()) {
               $article->update(['send_dshk' => true]);
           }else{
               $article->update(['send_dshk' => false]);
           }
           sleep(1);
        }
    }
}
