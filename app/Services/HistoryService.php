<?php

namespace App\Services;

use App\Enums\LogType;
use App\Repositories\HistoryRepository;
use App\Repositories\Interfaces\HistoryRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HistoryService
{
    private HistoryRepositoryInterface $repository;
    public function __construct($tableName)
    {
        $this->repository = new HistoryRepository($tableName);
    }

    public function getHistoryList(int $guId)
    {
        return $this->repository->getHistoryList(guId: $guId);
    }

    public function getFilteredList(int $guId, string $jsonColumn, $needle)
    {
        return $this->repository->getFilteredList(guId: $guId, jsonColumn: $jsonColumn, needle: $needle);
    }

    /**
     * Berilgan turlar bo'yicha tarix: gu_id va/yoki content ichidagi qiymatlar bo'yicha (masalan 'additionalInfo->parent_id' => 5).
     */
    public function getListByTypes(array $types, ?int $guId = null, array $content = [])
    {
        return $this->repository->getListByTypes(types: $types, guId: $guId, content: $content);
    }

    public function getHistory(int $id)
    {
        return $this->repository->getHistory(id: $id);
    }


    public function createHistory(int $guId, int $status, int $type, ?string $date, ?string $comment = "", mixed $additionalInfo = null): int
    {
        $content = match ($type) {
            LogType::TASK_HISTORY,
            LogType::ARTICLE_HISTORY,
            LogType::ARTICLE_INSPECTOR_HISTORY,
            LogType::ARTICLE_PRICE_DELETE,
            LogType::ARTICLE_PRICE_HISTORY,
            LogType::CLAIM_HISTORY,
            LogType::ARTICLE_ROTATION,
            LogType::ARTICLE_UPDATE_HISTORY,
            LogType::ARTICLE_CREATE_HISTORY,
            LogType::ARTICLE_MONITORING,
            LogType::ARTICLE_PAYMENT_CREATE,
            LogType::ARTICLE_DEADLINE_CHANGE,
            LogType::ARTICLE_LABORATORY_PRICE,
            LogType::ARTICLE_LABORATORY_PAYMENT,
            LogType::CHANGE_CONSUMER,
            LogType::BLOCK_DELETE,
            LogType::PRESENTATION_CREATE,
            LogType::PRESENTATION_UPDATE_TECHNIC,
            LogType::PRESENTATION_UPDATE_INSPECTION,
            LogType::PRESENTATION_UPDATE_INTERNAL,
            LogType::PRESENTATION_UPDATE_INSPECTOR,
            LogType::PRESENTATION_REJECT_INSPECTOR,
            LogType::PRESENTATION_REJECT_TECHNIC,
            LogType::PRESENTATION_REJECT_INTERNAL,
            LogType::PRESENTATION_REJECT_INSPECTION,
            LogType::PRESENTATION_SEND_INSPECTOR,
            LogType::PRESENTATION_UPDATE_EMPLOYEE,
            LogType::CHANGE_FUNDING_SOURCE,
            LogType::CHANGE_OBJECT_LOCATION,
            LogType::DECLARATION_CUSTOMER,
            LogType::DECLARATION_CONFIRMED,
            LogType::DECLARATION_CREATED,
            LogType::DECLARATION_UPDATED,
            LogType::DECLARATION_REJECTED,
            LogType::DECLARATION_CONSUMER,
            LogType::DECLARATION_CONSUMER_CHANGE,
            LogType::BLOCK_CREATE,
            LogType::DXA_CREATED,
            LogType::SEND_INSPECTOR,
            LogType::SEND_REGISTER,
            LogType::SEND_UPDATE,
            LogType::SEND_INSPECTION,
            LogType::DXA_REJECTED,
            LogType::DXA_ACCEPTED,
            LogType::DXA_CANCELLED,
            LogType::DXA_RESEND,
            LogType::HIDDEN_WORK_CREATE,
            LogType::HIDDEN_WORK_ACCEPT,
            LogType::HIDDEN_WORK_REJECT,
            LogType::HIDDEN_WORK_UPDATE,
            LogType::COMPLIANCE_HISTORY,
            LogType::CLAIM_RESEND,
            LogType::CLAIM_CONSUMER_CHANGE,
            LogType::LAB_INSTRUMENT_CREATE,
            LogType::LAB_INSTRUMENT_UPDATE,
            LogType::LAB_INSTRUMENT_STATUS,
            LogType::LAB_INSTRUMENT_DELETE,
            LogType::LAB_CERTIFICATE_CREATE,
            LogType::LAB_CERTIFICATE_UPDATE,
            LogType::LAB_CERTIFICATE_STATUS,
            LogType::LAB_CERTIFICATE_DELETE,
            => $this->shapeTaskContent(
                status: $status,
                comment: $comment,
                date: $date,
                additionalInfo: $additionalInfo,
            ),
            default => null,
        };


        if (!$content) {
            return false;
        }

       return $this->repository->createHistory(guId: $guId, content: $content, type: $type);
    }

    /**
     * Saqlanmagan (fill qilingan) modeldagi o'zgarishlar: ['field' => ['old' => ..., 'new' => ...]].
     * save()/update() dan OLDIN chaqiriladi. Har qanday Eloquent model uchun ishlaydi.
     */
    public static function modelChanges(Model $model, array $ignore = ['created_at', 'updated_at', 'updated_by']): array
    {
        $changes = [];
        foreach (array_keys($model->getDirty()) as $key) {
            if (in_array($key, $ignore, true)) continue;
            $old = self::plainValue($model->getOriginal($key));
            $new = self::plainValue($model->getAttribute($key));
            // json ustunlar: kalitlar tartibi (jsonb) farq qilsa ham qiymat bir xil bo'lsa o'zgarish emas
            if (is_array($old) && is_array($new) && $old == $new) continue;
            $changes[$key] = ['old' => $old, 'new' => $new];
        }
        return $changes;
    }

    /**
     * Yozuvning to'liq holati (o'chirishdan oldin saqlab qo'yish uchun).
     */
    public static function modelSnapshot(Model $model, array $ignore = ['created_at', 'updated_at']): array
    {
        return collect($model->getAttributes())
            ->except($ignore)
            ->map(fn($v, $key) => self::plainValue($model->getAttribute($key)))
            ->toArray();
    }

    private static function plainValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        return $value;
    }

    private function shapeTaskContent(int $status, string $comment, ?string $date, mixed $additionalInfo): array
    {
        $user = Auth::user();
        return [
            'user' => Auth::check() ? $user->id : "",
            'role' => $user ? (int)$user->getRoleFromToken() : null,
            'date' => ($date == null) ? now() : $date,
            'status' => $status,
            'comment' => $comment,
            'additionalInfo' => $additionalInfo,
            'requestData' => getRealData(),
        ];
    }

}
