<?php

namespace App\Repositories;

use App\Enums\UserRoleEnum;
use App\Helpers\DeclarationStatus;
use App\Models\Block;
use App\Models\Declaration;
use App\Models\DeclarationBlock;
use App\Models\DeclarationConclusion;
use App\Repositories\Interfaces\DeclarationInterface;

class DeclarationRepository implements DeclarationInterface
{
    public function __construct(
        protected Declaration $model,
        protected DeclarationConclusion $conclusion,
    ){}

    public function findById(int $id)
    {
        return $this->model->query()->find($id);
    }

    public function create($data)
    {
        $blocks = $data['blocks'] ?? [];
        unset($data['blocks']);

        $model = $this->model->query()->create($data);

        foreach ($blocks as $block) {
            DeclarationBlock::query()->create([
                'declaration_id'   => $model->id,
                'block_id'         => $block['block_id'] ?? null,
                'object_id'        => $model->object_id,
                'area'             => $block['area'] ?? null,
                'floor'            => $block['floor'] ?? null,
                'size'             => $block['size'] ?? null,
                'under_area'       => $block['under_area'] ?? null,
                'real_area'        => $block['real_area'] ?? null,
                'real_floor'       => $block['real_floor'] ?? null,
                'real_size'        => $block['real_size'] ?? null,
                'real_under_area'  => $block['real_under_area'] ?? null,
            ]);

             Block::query()->find($block['block_id'])->update(['is_declaration' => true]);
        }

        return $model;
    }


    public function all()
    {
        return $this->model->query();
    }

    public function change($user,  $roleId,  $id, $status)
    {
        $model = $this->findById($id);
        if (!$status) {
            $model->status = DeclarationStatus::REJECTED;
            $model->save();
            return $model;
        }

        $model->status = match ((int)$roleId) {
            UserRoleEnum::BUYURTMACHI->value => DeclarationStatus::CONFIRMED,
            UserRoleEnum::QURILISH->value   => DeclarationStatus::CUSTOMER,
            default => throw new \Exception('Role uchun status aniqlanmagan'),
        };

        $model->save();
        return $model;
    }

    public function update($id, array $data)
    {
        $blocks = $data['blocks'] ?? [];
        unset($data['blocks']);

        $model = $this->findById($id);
        $model->update($data);

        foreach ($blocks as $block) {
            DeclarationBlock::query()->updateOrCreate(
                [
                    'declaration_id' => $model->id,
                    'block_id'       => $block['block_id'],
                    'object_id'      => $model->object_id,
                ],
                [
                    'area'             => $block['area'] ?? null,
                    'floor'            => $block['floor'] ?? null,
                    'size'             => $block['size'] ?? null,
                    'under_area'       => $block['under_area'] ?? null,
                    'real_area'        => $block['real_area'] ?? null,
                    'real_floor'       => $block['real_floor'] ?? null,
                    'real_size'        => $block['real_size'] ?? null,
                    'real_under_area'  => $block['real_under_area'] ?? null,
                ]
            );
        }

        return $model;
    }

    public function concCreate($data)
    {
        return $this->conclusion->query()->create($data);
    }

    public function getConc($user, $roleId, $filters)
    {
        if (empty($filters)) return null;
        return $this->conclusion->query()
            ->when(isset($filters['id']), function ($query) use ($filters) {
                return $query->where('id', $filters['id']);
            })
            ->when(isset($filters['declaration_id']), function ($query) use ($filters) {
                $query->where('declaration_id', $filters['declaration_id']);
            })
            ->when(isset($filters['object_id']), function ($query) use ($filters) {
                 $query->where('object_id', $filters['object_id']);
            })
            ->when(isset($filters['role_id']), function ($query) use ($filters) {
                 $query->where('role_id', $filters['role_id']);
            })
            ->when(isset($filters['user_id']), function ($query) use ($filters) {
                 $query->where('user_id', $filters['user_id']);
            });
    }

}
