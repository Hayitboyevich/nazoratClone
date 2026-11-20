<?php

namespace App\Services;

use App\Http\Requests\ConcreteWorkRequest;
use App\Http\Requests\ConcreteWorkUpdateRequest;
use App\Repositories\Interfaces\ConcreteWorkInterface;

class ConcreteWorkService
{
    public function __construct(protected ConcreteWorkInterface $repository)
    {}

    public function all($user, $roleId, $filters)
    {
        $query = $this->repository->all();
        return $this->repository->filters($query, $filters);
    }

    public function create(ConcreteWorkRequest $request)
    {
        $model = $this->repository->create($request->except(['images']));

        if ($request->hasFile('images')) {
            $files = collect($request->file('images'))
                ->map(fn($file) => ['url' => $file->store('concrete/images', 'public')])
                ->all();

            $model->images()->createMany($files);
        }

        return $model;

    }

    public function update(ConcreteWorkUpdateRequest $request)
    {
        try {
            $model = $this->repository->findById($request->id);

            $model->update([
                'lab_status' => $request->lab_status,
            ]);

            if ($request->hasFile('files')) {
                $files = collect($request->file('files'))
                    ->map(fn($file) => ['url' => $file->store('concrete/documents', 'public')])
                    ->all();

                $model->documents()->createMany($files);
            }

            return $model;
        }catch (\Exception $exception){
            throw $exception;
        }
    }
}
