<?php

namespace App\Models;

use App\Enums\LabTestContractStatusEnum;
use App\Enums\LabTestTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Laboratoriya arizasi va shartnomasi (eski LabContract ga bog'liq emas).
 * Bitta ariza = bitta shartnoma, ilovasi bloklar bo'yicha ketma-ket hisoblanadi.
 */
class LabTestContract extends Model
{
    use SoftDeletes;

    public const CUSTOMER_LEGAL = 1;      // yuridik shaxs - STIR (9 xonali)
    public const CUSTOMER_INDIVIDUAL = 2; // jismoniy shaxs - JShShIR (14 xonali)

    protected $guarded = [];

    protected $casts = [
        'status' => 'integer',
        'object_type_id' => 'integer',
        'expertise_exists' => 'boolean',
        'customer_type' => 'integer',
        'test_type' => 'integer',
        'object_data' => 'array',
        'documents' => 'array',
        'return_data' => 'array',
        'vat_percent' => 'integer',
        'total_price' => 'integer',
        'total_vat' => 'integer',
        'total_with_vat' => 'integer',
        'confirmed_at' => 'datetime',
    ];

    protected $appends = ['status_name', 'test_type_name', 'is_returned', 'is_additional'];

    // object_data - eski oqimdan qolgan, endi blok ma'lumotlari lab_test_contract_blocks da
    protected $hidden = ['deleted_at', 'object_data'];

    public function blocks(): HasMany
    {
        return $this->hasMany(LabTestContractBlock::class)->orderBy('sort')->orderBy('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LabTestContractItem::class)->orderBy('lab_test_contract_block_id')->orderBy('work_type_id')->orderBy('id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(LabTestContractHistory::class)->orderByDesc('id');
    }

    public function tests(): HasMany
    {
        return $this->hasMany(LabTest::class, 'lab_contract_id');
    }

    /** Asosiy shartnoma (qo'shimcha shartnomada) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->select('id', 'contract_number', 'status', 'confirmed_at', 'parent_id');
    }

    /** Shu (asosiy) shartnomaga tuzilgan qo'shimcha shartnomalar */
    public function additionals(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('id');
    }

    /** Qo'shimcha shartnoma: asosiy shartnomaga keyin qo'shilgan blok va sinovlar */
    public function getIsAdditionalAttribute(): bool
    {
        return $this->parent_id !== null;
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'object_id');
    }

    /** Mas'ul laborant - blok va ishlarni kiritadi, keyin shu shartnoma bo'yicha sinov o'tkazadi */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id')->select('id', 'name', 'surname', 'middle_name');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->select('id', 'name', 'surname', 'middle_name');
    }

    public function getStatusNameAttribute(): ?string
    {
        return LabTestContractStatusEnum::tryFrom((int)$this->status)?->label();
    }

    public function getTestTypeNameAttribute(): ?string
    {
        return LabTestTypeEnum::tryFrom((int)$this->test_type)?->label();
    }

    /** Buxgalter yoki inspeksiya boshlig'i laborantga qaytargan va laborant hali qayta yubormagan */
    public function getIsReturnedAttribute(): bool
    {
        return (int)$this->status === LabTestContractStatusEnum::LABORANT->value && !empty($this->return_data);
    }

    public function statusEnum(): LabTestContractStatusEnum
    {
        return LabTestContractStatusEnum::from((int)$this->status);
    }

    /**
     * Statusni o'zgartirish va/yoki ma'lumotlarni yangilash - har doim tarixga yoziladi.
     * $to berilmasa status o'zgarmaydi. $data - tarixga yoziladigan qo'shimcha ma'lumot.
     */
    public function changeStatus(?LabTestContractStatusEnum $to, ?int $userId, ?int $roleId, ?string $comment = null, array $attributes = [], ?array $data = null): LabTestContractHistory
    {
        $from = $this->statusEnum();
        $to ??= $from;

        if ($to !== $from && !$from->canTransitionTo($to)) {
            throw new InvalidArgumentException("Ariza holatini «{$from->label()}» dan «{$to->label()}» ga o'zgartirib bo'lmaydi");
        }

        return DB::transaction(function () use ($from, $to, $userId, $roleId, $comment, $attributes, $data) {
            $this->fill($attributes);
            $this->status = $to->value;
            $this->updated_by = $userId;
            if ($to === LabTestContractStatusEnum::CONFIRMED && $to !== $from) {
                $this->confirmed_at = now();
            }
            $this->save();

            return $this->histories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'comment' => $comment,
                'data' => $data,
                'user_id' => $userId,
                'role_id' => $roleId,
            ]);
        });
    }
}
