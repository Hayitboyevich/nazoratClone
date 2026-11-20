<?php

namespace App\Models;

use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Helpers\ClaimStatuses;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class Article extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];
    protected $appends = ['cost', 'closed_type', 'lab_cost', 'closed_file'];

    protected static function booted(): void
    {
        static::updated(function (Article $article) {
            if ($article->wasChanged('shaffof_mobile')) {
                return;
            }
            $article->updateQuietly([
                'shaffof_mobile' => null,
            ]);
        });
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function signatures(): MorphMany
    {
        return $this->morphMany(DocumentSignature::class, 'signable');
    }

    protected $casts = [
        'object_status_id' => ObjectStatusEnum::class,
    ];

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function declaration(): HasOne
    {
        return $this->hasOne(Declaration::class, 'object_id');
    }

    public function presentations(): HasMany
    {
        return $this->hasMany(Presentation::class, 'object_id');
    }

    public function getCostAttribute()
    {
        $totalPaid = $this->paymentLogs->sum(function ($log) {
            return isset($log->content->additionalInfo->amount)
                ? (float)$log->content->additionalInfo->amount
                : 0;
        });

        $totalAmount = (float)$this->price_supervision_service;

        $notPaid = $totalAmount - $totalPaid;

        if (trim($this->price_supervision_service) === '0.00') {
            return 0;
        }

        return $notPaid;
    }

    public function getLabCostAttribute()
    {
        $totalPaid = $this->paymentLaboratories->sum(function ($log) {
            return isset($log->content->additionalInfo->amount)
                ? (float)$log->content->additionalInfo->amount
                : 0;
        });

        $totalAmount = (float)$this->laboratory_cost;

        $notPaid = $totalAmount - $totalPaid;

        return $notPaid;
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class, 'object_id');
    }

    public function govClaims(): HasMany
    {
        return $this->hasMany(ClaimGov::class, 'object_id');
    }

    public function getClosedTypeAttribute()
    {
        $type = "-";

        if (ManualConfirmedClaim::query()->where('object_id', $this->id)->first())
            $type = "Inspeksiya boshlig'i";

        if (Claim::query()->where(['status' => ClaimStatuses::TASK_STATUS_CONFIRMED, 'object_id' => $this->id])->first())
            $type = "YIDXP";

        return $type;
    }

    public function getClosedFileAttribute()
    {

        $manual = ManualConfirmedClaim::query()
            ->where('object_id', $this->id)
            ->first();

        if ($manual && !empty($manual->file)) {
            return Storage::disk('public')->url($manual->file);
        }

        $claim = Claim::query()
            ->where('object_id', $this->id)
            ->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED)
            ->exists();
        if ($claim) {
            return "https://api-nazorat.mc.uz/api/pdf-claim/" . $this->id;
        }

        $claimGov = ClaimGov::query()
            ->where('object_id', $this->id)
            ->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED)
            ->exists();

        if ($claimGov) {
           return  config('app.url') . '/api/pdf-dalolatnoma/' . $this->id;
        }

        $history = $this->histories()
            ->where('type', LogType::ARTICLE_HISTORY)
            ->orderByDesc('created_at')
            ->get()
            ->first(function ($history) {
                return data_get($history->content, 'additionalInfo.new_status')
                    == ObjectStatusEnum::SUBMITTED->value;
            });

        if ($history) {

            $document = Document::query()
                ->where('documentable_id', $history->id)
                ->where('documentable_type', ArticleHistory::class)
                ->latest()
                ->first();

            if ($document && !empty($document->url)) {
                return Storage::disk('public')->url($document->url);
            }
        }

        return '#';

    }

    public function getInternalControlAttribute()
    {
        return $this->userHistories()
            ->where('role_id', UserRoleEnum::ICHKI->value)
            ->pluck('check_at');
    }

    public function getAuthorControlAttribute()
    {
        return $this->userHistories()
            ->where('role_id', UserRoleEnum::MUALLIF->value)
            ->pluck('check_at');
    }

    public function getTechnicControlAttribute()
    {
        return $this->userHistories()
            ->where('role_id', UserRoleEnum::TEXNIK->value)
            ->pluck('check_at');
    }

    public function userHistories(): HasMany
    {
        return $this->hasMany(ObjectUserHistory::class, 'object_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ArticleHistory::class, 'gu_id');
    }

    public function inspector(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_users', 'article_id', 'user_id')->withPivot('role_id')->wherePivot('role_id', 3);
    }

    public function labContract(): BelongsTo
    {
        return $this->belongsTo(LabContract::class, 'lab_contract_id');
    }

    public function organization(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_users', 'article_id', 'user_id')->withPivot('role_id')->wherePivot('role_id', 8);
    }

    public function designer(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_users', 'article_id', 'user_id')->withPivot('role_id')->wherePivot('role_id', 9);
    }

    public function applicantUser(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_users', 'article_id', 'user_id')->withPivot('role_id')->wherePivot('role_id', 38);
    }

    public function vendor(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_users', 'article_id', 'user_id')->withPivot('role_id')->wherePivot('role_id', 10);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'article_users', 'article_id', 'user_id')->withPivot('role_id');
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(DxaResponse::class, 'dxa_response_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'article_users', 'article_id', 'role_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function objectStatus(): BelongsTo
    {
        return $this->belongsTo(ObjectStatus::class);
    }

    public function difficulty(): BelongsTo
    {
        return $this->belongsTo(DifficultyCategory::class, 'difficulty_category_id');
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class, 'funding_source_id');
    }

    public function objectSector(): BelongsTo
    {
        return $this->belongsTo(ObjectSector::class, 'object_sector_id');
    }

    public function scopeSearchByName($query, $searchTerm)
    {
        $searchTerm = strtolower($searchTerm);

        return $query->whereRaw('LOWER(articles.name) LIKE ?', ['%' . $searchTerm . '%']);

    }

    public function scopeSearchByOrganization($query, $searchTerm)
    {
        $searchTerm = strtolower($searchTerm);

        return $query->whereRaw('LOWER(articles.organization_name) LIKE ?', ['%' . $searchTerm . '%']);

    }

    public function scopeSearchByInn($query, $searchTerm)
    {
        $searchTerm = strtolower($searchTerm);

        return $query->whereHas('users', function ($query) use ($searchTerm) {
            $query->whereIn('role_id', [
                UserRoleEnum::BUYURTMACHI->value,
                UserRoleEnum::QURILISH->value,
                UserRoleEnum::LOYIHA->value
            ])
                ->whereRaw('LOWER(pinfl) LIKE ?', ['%' . $searchTerm . '%']);
        });

    }

    public function scopeSearchByCustomerName($query, $searchTerm)
    {
        $searchTerm = strtolower($searchTerm);

        return $query->whereRaw('LOWER(articles.organization_name) LIKE ?', ['%' . $searchTerm . '%'])
            ->orWhereRaw('LOWER(articles.name) LIKE ?', ['%' . $searchTerm . '%']);

    }


    public function scopeSearchByTaskId($query, $searchTerm)
    {
        return $query->where('articles.task_id', 'like', '%' . $searchTerm . '%')
            ->orWhere('articles.id', 'like', '%' . $searchTerm . '%')
            ->orWhere('articles.manual_task_id', 'like', '%' . $searchTerm . '%');
    }


    public function regulations(): HasMany
    {
        return $this->hasMany(Regulation::class, 'object_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(CheckListAnswer::class, 'object_id');
    }

    public function sphere(): BelongsTo
    {
        return $this->belongsTo(Sphere::class, 'sphere_id');
    }

    public function objectType(): BelongsTo
    {
        return $this->belongsTo(ObjectType::class, 'object_type_id');
    }

    public function violations(): HasManyThrough
    {
        return $this->hasManyThrough(Violation::class, Regulation::class, 'object_id');
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(Monitoring::class, 'object_id');
    }

    public function paymentLogs(): HasMany
    {
        return $this->hasMany(ArticleHistory::class, 'gu_id')->where('type', LogType::ARTICLE_PAYMENT_CREATE);
    }

    public function paymentLaboratories(): HasMany
    {
        return $this->hasMany(ArticleHistory::class, 'gu_id')->where('type', LogType::ARTICLE_LABORATORY_PAYMENT);
    }

    public function totalAmount()
    {
        return $this->paymentLogs()
            ->get()
            ->sum(function ($log) {
                return $log->content->additionalInfo->amount ?? 0;
            });
    }

    public function scopeWithPaymentStats(Builder $query)
    {
        return $query->with(['paymentLogs' => function ($query) {
            $query->select('gu_id', DB::raw('SUM(CAST(content->additionalInfo->>amount AS DECIMAL)) as total_paid'))
                ->groupBy('gu_id');
        }]);
    }

    public function getPaymentStatusAttribute()
    {
        $totalPaid = $this->paymentLogs->sum('total_paid');
        $priceSupervisionService = (float)$this->price_supervision_service;

        if ($totalPaid === 0) {
            return 1;
        } elseif ($totalPaid < $priceSupervisionService) {
            return 2;
        } else {
            return 3;
        }
    }


}
