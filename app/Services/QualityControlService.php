<?php

namespace App\Services;

use App\Http\Requests\QualityControlRequest;
use App\Http\Requests\QualityControlUpdateRequest;
use App\Repositories\Interfaces\QualityControlInterface;
use Illuminate\Support\Facades\DB;

class QualityControlService
{
    public function __construct(
        public QualityControlInterface $repository
    )
    {

    }

    public function all($filters, $user, $roleId)
    {
        $query = $this->repository->all($roleId);
        return $this->repository->filter($query, $filters);
    }

    public function create(QualityControlRequest $request)
    {
        DB::beginTransaction();
        try {
            $quality = $this->repository->create($request->except(['images', 'docs', 'contract_images']));
            if ($request->hasFile('images')) {
                $images = collect($request->file('images'))
                    ->map(fn($file) => ['url' => $file->store('quality/images', 'public')])
                    ->all();

                $quality->images()->createMany($images);
            }

            if ($request->hasFile('docs')) {
                $images = collect($request->file('docs'))
                    ->map(fn($file) => ['url' => $file->store('quality/documants', 'public')])
                    ->all();

                $quality->documents()->createMany($images);
            }

            if ($request->hasFile('contract_images')) {
                $this->saveFiles($quality, $request->file('contract_images'), 'contract_images');
            }
            DB::commit();
            return $quality;

        }catch (\Exception $exception){
            DB::rollBack();
            throw  $exception;
        }

    }

    public function update(QualityControlUpdateRequest $request)
    {
        try {
            $control = $this->repository->findById($request->id);
            $this->repository->update($control->id, $request->except(['files']));

            if ($request->hasFile('files')) {
                $this->saveFiles($control, $request->file('files'), 'lab_files');
            }

            return $control;
        }catch (\Exception $exception){
            throw  $exception;
        }
    }

    private function saveFiles($quality, $files, $column)
    {
        $array = [];
        foreach ($files as $file) {
            $path = $file->store('quality/files', 'public');
            $array[] = $path;
        }

        $quality->update([
            $column => json_encode($array),
        ]);
    }
}
