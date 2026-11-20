<?php

namespace App\Repositories;

use App\Enums\LogType;
use App\Repositories\Interfaces\HistoryRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HistoryRepository implements HistoryRepositoryInterface
{
    protected $table;

    public function __construct($table)
    {
        $this->table = $table;
    }

    public function getHistoryList(int $guId)
    {
        return DB::table($this->table)->where('gu_id', $guId)->whereIn('type', [
            LogType::TASK_HISTORY,
            LogType::COMPLIANCE_HISTORY,
            LogType::CLAIM_RESEND,
            LogType::CLAIM_CONSUMER_CHANGE
        ])->orderBy('id', 'asc')->get([
            'id',
            'gu_id',
            'content',
            'created_at'
        ]);
    }

    public function getFilteredList(int $guId, string $jsonColumn, $needle)
    {
        return DB::table($this->table)->where('gu_id', $guId)
            ->where("content->$jsonColumn", $needle)
            ->orderBy('id', 'desc')
            ->get([
                'id',
                'gu_id',
                'content',
                'created_at'
            ]);
    }

    public function getListByTypes(array $types, ?int $guId = null, array $content = [])
    {
        $query = DB::table($this->table)
            ->whereIn('type', $types)
            ->when($guId, fn($q) => $q->where('gu_id', $guId));

        foreach ($content as $path => $value) {
            $query->where("content->$path", (string)$value);
        }

        return $query->orderBy('id')->get(['id', 'gu_id', 'type', 'content', 'created_at']);
    }

    public function getHistory(int $id)
    {
        return DB::table($this->table)->where('id', $id)->where('type', LogType::TASK_HISTORY)->first([
            'id',
            'gu_id',
            'content',
            'created_at'
        ]);
    }

    public function createHistory(int $guId, array $content, int $type): int
    {
        return DB::table($this->table)->insertGetId([
            'gu_id' => $guId,
            'content' => json_encode($content),
            'type' => $type,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now()
        ]);
    }
}
