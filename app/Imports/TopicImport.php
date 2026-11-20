<?php

namespace App\Imports;

use App\Enums\UserRoleEnum;
use App\Models\Article;
use App\Models\ClaimSphere;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class TopicImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        $cadastrals = $rows
            ->pluck('kad')
            ->filter()
            ->unique()
            ->values();

        $objects = Article::query()
            ->whereIn('cadastral_number', $cadastrals)
            ->get([
                'id',
                'cadastral_number',
                'task_id',
                'manual_task_id',
            ])
            ->keyBy('cadastral_number');

        $path = storage_path('app/cadastral_tasks.csv');

        $file = fopen($path, 'a');

        foreach ($rows as $row) {

            $cadastralNumber = $row['kad'] ?? null;

            if (!$cadastralNumber) {
                continue;
            }

            $object = $objects->get($cadastralNumber);

            if (!$object) {
                continue;
            }

            fputcsv($file, [
                $cadastralNumber,
                $object->task_id ?? $object->manual_task_id,
            ]);
        }

        fclose($file);
    }

}
