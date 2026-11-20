<?php

namespace App\Helpers;

use App\Models\Article;
use App\Models\Block;
use App\Models\Claim;
use App\Models\ClaimGov;
use App\Models\ClaimGovOrganizationReview;
use App\Models\ClaimOrganizationReview;
use App\Models\Declaration;
use App\Models\DxaResponse;
use App\Models\Holiday;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ModelResolver
{
    private const MODELS = [
        'articles' => Article::class,
        'declarations' => Declaration::class,
        'holidays' => Holiday::class,
        'dxa_responses' => DxaResponse::class,
        'claim_organization_reviews' => ClaimOrganizationReview::class,
        'claim_gov_organization_reviews' => ClaimGovOrganizationReview::class,
        'claims' => Claim::class,
        'claim_govs' => ClaimGov::class,
        'blocks' => Block::class,
    ];

    public static function resolve(string $table): Model
    {
        $model = self::MODELS[$table] ?? null;

        if (!$model) {
            throw ValidationException::withMessages([
                'table' => ['Hujjat turi noto‘g‘ri ko‘rsatilgan.'],
            ]);
        }

        return new $model();
    }

    public static function find(string $table, int|string $id): Model
    {
        return self::resolve($table)
            ->newQuery()
            ->findOrFail($id);
    }
}
