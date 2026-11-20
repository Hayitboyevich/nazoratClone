<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabTestCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];


    public function price()
    {
        return $this->hasMany(LabTestPrice::class, 'test_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function parents()
    {
        return $this->parent()->whereNotNull('parent_id')->with('parents');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public static function tree($parentId = null, $regionId = null)
    {
        $categories = self::orderBy('id')->get()->groupBy('parent_id');


        $prices = LabTestPrice::when($regionId, function ($q) use ($regionId) {
            $q->where('region_id', $regionId);
        })->get()->groupBy('test_id');

        $buildTree = function ($parentId) use (&$buildTree, $categories, $prices) {
            return ($categories->get( $parentId) ?? collect())->map(function ($category) use (&$buildTree, $prices) {
                $category->children = $buildTree($category->id);

                if ($category->children->isEmpty()) {
                    $price = $prices->get($category->id)?->first();
                    if ($price) {
                        $category->price = (object)[
                            'id' => $price->id,
                            'price' => $price->price,
                            'test_id' => $price->test_id,
                            'region_id' => $price->region_id
                        ];
                    }
                }

                return $category;
            });
        };


        if (is_null($parentId)) {
            return $buildTree(null);
        }


        if ($parentId == 120) {

            $parent = self::find(120);

            if (!$parent) {
                return collect();
            }

            $children = collect();

            foreach ([1, 2] as $id) {
                $children = $children->merge($buildTree($id));
            }

            $parent->children = $children->values();

            return collect([$parent]);
        }

        $parent = self::find($parentId);
        if (!$parent) {
            return collect();
        }

        $parent->children = $buildTree($parent->id);

        if ($parent->children->isEmpty()) {
            $price = $prices->get($parent->id)?->first();
            if ($price) {
                $parent->price = (object)[
                    'id' => $price->id,
                    'price' => $price->price,
                    'test_id' => $price->test_id,
                    'region_id' => $price->region_id
                ];
            }
        }

        return collect([$parent]);
    }




    public function getAllDescendants()
    {
        $descendants = collect();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }

        return $descendants;
    }

    public function getAllDescendantsIds()
    {
        return $this->getAllDescendants()->pluck('id');
    }

}
