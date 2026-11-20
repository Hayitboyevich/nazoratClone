<?php

namespace App\Models;

use App\Enums\LabTestResultEnum;
use App\Enums\LabTestStatusEnum;
use App\Enums\UserRoleEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Laboratoriya sinovi. Usulga xos ma'lumotlar input_data / result_data (jsonb) da saqlanadi.
 */
class LabTest extends Model
{
    use SoftDeletes;

    /** Bayonnomani tasdiqlash / rad etish / tahrirga qaytarish huquqiga ega rollar */
    public const REVIEWER_ROLES = [
        UserRoleEnum::INSPEKSIYA->value,
        UserRoleEnum::REG_LAB->value,
    ];

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'work_type_id' => 'integer',
        'floor' => 'integer',
        'result' => 'integer',
        'test_date' => 'date:Y-m-d',
        'indicator_ids' => 'array',
        'instrument_ids' => 'array',
        'input_data' => 'array',
        'result_data' => 'array',
        'protocol_formed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = ['status_name', 'result_name'];

    protected $hidden = ['deleted_at'];

    public function method(): BelongsTo
    {
        return $this->belongsTo(LabMethod::class, 'lab_method_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(LabTestContract::class, 'lab_contract_id')
            ->select('id', 'contract_number', 'object_name', 'object_id', 'region_id', 'status');
    }

    /** Shartnoma ilovasining qatori - sinov shu qatorda tanlangan usul va sinovlar bo'yicha o'tkaziladi */
    public function contractItem(): BelongsTo
    {
        return $this->belongsTo(LabTestContractItem::class, 'lab_contract_item_id');
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(LabTestContractBlock::class, 'lab_contract_block_id')->select('id', 'name', 'floors_count');
    }

    /** Ish turi (bosqich): Kotlovan, Fundament, Konstruksiya elementlari ... */
    public function workType(): BelongsTo
    {
        return $this->belongsTo(WorkType::class)->select('id', 'name', 'is_multiple_floor');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->select('id', 'name', 'surname', 'middle_name');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->select('id', 'name', 'surname', 'middle_name');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LabTestStatusHistory::class)->orderByDesc('id');
    }

    public function getStatusNameAttribute(): ?string
    {
        return LabTestStatusEnum::tryFrom((int)$this->status)?->label();
    }

    public function getResultNameAttribute(): ?string
    {
        return $this->result ? LabTestResultEnum::tryFrom((int)$this->result)?->label() : null;
    }

    public function statusEnum(): LabTestStatusEnum
    {
        return LabTestStatusEnum::from((int)$this->status);
    }

    /**
     * Statusni o'zgartirish va/yoki ma'lumotlarni tahrirlash - har doim tarixga yoziladi.
     * $to berilmasa status o'zgarmaydi (faqat tahrir). $attributes - input_data, result_data, protocol_* va h.k.
     * O'zgarishdan oldingi input_data / result_data tarixning old_data ustunida saqlanadi.
     */
    public function changeStatus(?LabTestStatusEnum $to, ?int $userId = null, ?int $roleId = null, ?string $comment = null, array $attributes = []): LabTestStatusHistory
    {
        $from = $this->statusEnum();
        $to ??= $from;

        if ($to !== $from && !$from->canTransitionTo($to)) {
            throw new InvalidArgumentException("Sinov holatini «{$from->label()}» dan «{$to->label()}» ga o'zgartirib bo'lmaydi");
        }

        return DB::transaction(function () use ($from, $to, $userId, $roleId, $comment, $attributes) {
            $old = [
                'input_data' => $this->input_data,
                'result_data' => $this->result_data,
            ];

            $this->fill($attributes);
            $dataChanged = $this->isDirty(['input_data', 'result_data']);
            $this->status = $to->value;
            $this->updated_by = $userId;

            if ($to === LabTestStatusEnum::PROTOCOL_FORMED && $to !== $from) {
                $this->protocol_formed_at = now();
                $this->reviewed_by = null;
                $this->reviewed_at = null;
            }
            if ($from === LabTestStatusEnum::PROTOCOL_FORMED && $to !== $from) {
                $this->reviewed_by = $userId;
                $this->reviewed_at = now();
            }

            $this->save();

            return $this->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'old_data' => $dataChanged ? $old : null,
                'comment' => $comment,
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        });
    }
}
