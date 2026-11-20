<?php

namespace App\Http\Controllers\Api;

use App\Enums\LogType;
use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\NotFoundException;
use App\Exports\HiddenWorkExport;
use App\Http\Requests\BlockModeRequest;
use App\Http\Requests\BlockRequest;
use App\Http\Requests\FoundationBlockRequest;
use App\Http\Requests\HiddenWorkRequest;
use App\Http\Resources\ArticleBlockResource;
use App\Http\Resources\BlockModeResource;
use App\Http\Resources\BlockResource;
use App\Http\Resources\BlockTypeResource;
use App\Http\Resources\FoundationResource;
use App\Http\Resources\HiddenWorkResource;
use App\Http\Resources\ResponseBlockResource;
use App\Models\Article;
use App\Models\ArticleHistory;
use App\Models\Block;
use App\Models\BlockMode;
use App\Models\BlockType;
use App\Models\ConcreteWork;
use App\Models\DocumentSignature;
use App\Models\DxaResponse;
use App\Models\FoundationBlock;
use App\Models\HiddenWorksAct;
use App\Services\ArticleService;
use App\Services\EimzoService;
use App\Services\HistoryService;
use App\Services\ShaffofIdService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BlockController extends BaseController
{
    private HistoryService $objectHistory;

    public function __construct(protected EimzoService $imzoService, protected ShaffofIdService $shaffofIdService)
    {
        $this->middleware('auth');
        $this->objectHistory = new HistoryService('article_histories');
        parent::__construct();
    }

    public function index($id): JsonResponse
    {
        try {
            $article = Article::find($id);

            if (!$article) throw new NotFoundException('Article not found', 404);

            $blocks = $article->blocks()->get();

            if ($blocks->isEmpty()) throw new NotFoundException('Blocks not found', 404);

            return $this->sendSuccess(ArticleBlockResource::collection($blocks), 'All Blocks');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }

    }

    public function responseBlock($id): JsonResponse
    {
        try {
            $response = DxaResponse::query()->findOrFail($id);
            if (!$response) throw new NotFoundException('Response not found', 404);
            $blocks = $response->blocks()->get();
            if ($blocks->isEmpty()) throw new NotFoundException('Blocks not found', 404);

            return $this->sendSuccess(ResponseBlockResource::collection($blocks), 'All Blocks');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function create(BlockRequest $request)
    {
        DB::beginTransaction();
        try {

            $document = DocumentSignature::query()
                ->where('guid', $request->guid)
                ->where('uuid', $request->uuid)
                ->where('user_id', $this->user->id)
                ->where('role_id', $this->roleId)
                ->first();

            if (!$document) {
                throw new \Exception('Tasdiqlash uchun hujjat topilmadi.');
            }

            if ($document->status !== 'signed') {
                $signed = $this->shaffofIdService->checkDocumentSign(
                    $this->user,
                    $this->roleId,
                    $request->guid,
                   $request->uuid,
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }
            $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

            $block = Block::create($request->except(['images', 'files', 'uuid', 'guid']));

            $addInfo = [
                'id' => $block->id,
                'name' => $block->name,
                'floor' => $block->floor,
                'count_apartments' => $block->count_apartments,
                'construction_area' => $block->construction_area,
                'selected_work_type' => $block->selected_work_type,
                'meta' => $meta,
            ];

            $this->objectHistory->createHistory(
                guId: $block->article_id,
                status: $block->object->object_status_id->value,
                type: LogType::BLOCK_CREATE,
                date: null,
                comment: "",
                additionalInfo: $addInfo
            );

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('images/block', 'public');
                    $block->images()->create(['url' => $path]);
                }
            }

            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $path = $file->store('documents/block', 'public');
                    $block->documents()->create(['url' => $path]);
                }
            }

            DB::commit();
            return $this->sendSuccess(new BlockResource($block), 'Block created');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }

    }

    public function delete($id, Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $document = DocumentSignature::query()
                ->where('guid', $request->guid)
                ->where('uuid', $request->uuid)
                ->where('user_id', $this->user->id)
                ->where('role_id', $this->roleId)
                ->first();

            if (!$document) {
                throw new \Exception('Tasdiqlash uchun hujjat topilmadi.');
            }

            if ($document->status !== 'signed') {
                $signed = $this->shaffofIdService->checkDocumentSign(
                    $this->user,
                    $this->roleId,
                    $request->guid,
                    $request->uuid,
                );

                if (!$signed) {
                    throw new \Exception('Hujjat imzolanmagan yoki tasdiqlashda xatolik yuz berdi.');
                }
            }

            $meta = ['guid' => $document->guid, 'uuid' => $document->uuid];

            $block = Block::find($id);

            if (!$block) throw new NotFoundException('Block not found', 404);
            if ($block->accepted == true) {
                return $this->sendError('Blok topshirilgan');
            }
            $object = Article::query()->findOrFail($block->article_id);
            if ($object->object_status_id == ObjectStatusEnum::PROGRESS) {

                $addInfo = [
                    'id' => $block->id,
                    'name' => $block->name,
                    'floor' => $block->floor,
                    'count_apartments' => $block->count_apartments,
                    'construction_area' => $block->construction_area,
                    'selected_work_type' => $block->selected_work_type,
                    'meta' => $meta,
                ];

                $historyId = $this->objectHistory->createHistory(
                    guId: $object->id,
                    status: $object->object_status_id->value,
                    type: LogType::BLOCK_DELETE,
                    date: null,
                    comment: $request->comment ?? "",
                    additionalInfo: $addInfo
                );

                $history = ArticleHistory::query()->findOrFail($historyId);

                if (request()->hasFile('file')) {
                    $path = request()->file('file')->store('object/files', 'public');
                    $history->documents()->create(['url' => $path]);
                }

                $block->delete();
                DB::commit();
                return $this->sendSuccess(null, 'Block deleted');
            }
            return $this->sendError('Obyekt jarayonda emas');
        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage());
        }
    }

    public function edit(): JsonResponse
    {
        try {
            $block = Block::find(request('id'));
            if (!$block) throw new NotFoundException('Block not found', 404);
            $block->update([
                'name' => request('name'),
            ]);
            return $this->sendSuccess(new BlockResource($block), 'Block updated');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function addModeToBlock(BlockModeRequest $request): JsonResponse
    {
        try {
            Block::query()->findOrFail(request('id'))->update([
                'block_mode_id' => $request->mode,
                'block_type_id' => $request->type,
                'floor' => $request->floor ?? null,
                'count_apartments' => $request->count_apartments ?? null,
            ]);
            return $this->sendSuccess([], 'Success');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }


    public function blockModes(): JsonResponse
    {
        try {
            $modes = BlockMode::all();
            return $this->sendSuccess(BlockModeResource::collection($modes), 'All Block Modes');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function blockTypes(): JsonResponse
    {
        try {
            $types = BlockType::query()->where('block_mode_id', request('id'))->get();
            return $this->sendSuccess(BlockTypeResource::collection($types), 'All Block Modes');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function getBlock(): JsonResponse
    {
        try {
            $block = Block::query()->findOrFail(request('id'));
            if (!$block) throw new NotFoundException('Block not found', 404);
            return $this->sendSuccess(new ArticleBlockResource($block), 'Block');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage());
        }
    }

    public function foundationCreate(FoundationBlockRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            $foundationBlock = FoundationBlock::query()->where('block_id', $request->block_id)->where('status', 1)->first();

            if ($foundationBlock) return $this->sendError('Blok uchun kotlovan xolati mavjud', 404);

            $foundation = FoundationBlock::query()->create([
                'object_id' => $request->object_id,
                'block_id' => $request->block_id,
                'foundation_depth' => $request->foundation_depth,
                'foundation_slope' => $request->foundation_slope,
                'status' => 1,
                'user_id' => $this->user->id,
                'role_id' => $this->roleId,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('images/foundation', 'public');
                    $foundation->images()->create(['url' => $path]);
                }
            }
            $this->saveFiles($request['file'], $request['act_file'], $request['geo_file'], $request['act'], $foundation);

            $block = Block::query()->findOrFail($foundation->block_id);

            $data = [
                'work_type_id' => 2,
                'user_id' => $this->user->id,
                'created_at' => now()
            ];

            $checklistData = $block->checklists ?? [];

            $checklistData[] = $data;

            $block->update([
                'foundation' => true,
                'checklists' => $checklistData,
            ]);

            DB::commit();

            return $this->sendSuccess(FoundationResource::make($foundation), 'Foundation created');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function foundationBlock()
    {
        try {
            $items = request()->only('block_id', 'object_id');
            $foundationBlock = FoundationBlock::query()
                ->where('object_id', $items['object_id'])
                ->whereHas('block')
                ->when(isset($items['block_id']), function ($query) use ($items) {
                    $query->where('block_id', $items['block_id']);
                })->get();


            return $this->sendSuccess(FoundationResource::collection($foundationBlock), 'Foundation block');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function blockChecklist($id)
    {
        try {
            $block = Block::query()->findOrFail($id);

            $data = [
                'work_type_id' => (int) request('work_type_id'),
                'floor'        => request('floor'),
                'user_id'      => (int) $this->user->id,
                'created_at'   => now(),
            ];

            if (request('has_action') == 1) {
                $actionData = $block->actions ?? [];

                $actionData[] = $data;

                $block->update([
                    'actions' => $actionData,
                ]);
            } else {
                $checklistData = $block->checklists ?? [];

                $checklistData[] = $data;

//                if (!$block->concreteWorks()
//                    ->where('work_type_id', $data['work_type_id'])
//                    ->where('floor', $data['floor'])
//                    ->exists()
//                ) {
//                    return $this->sendError(
//                        'Bu ish turi uchun beton ishlari jurnali yuritilmagan'
//                    );
//                }
//
//                if (!$block->hiddenWorks()
//                    ->where('work_type_id', $data['work_type_id'])
//                    ->where('floor', $data['floor'])
//                    ->exists()
//                ) {
//                    return $this->sendError(
//                        'Bu ish turi uchun yopiq ishlar jurnali yuritilmagan'
//                    );
//                }

                $block->update([
                    'checklists' => $checklistData,
                ]);
            }

            return $this->sendSuccess([], 'Success');

        } catch (\Exception $exception) {
            return $this->sendError(
                'Xatolik aniqlandi',
                $exception->getMessage()
            );
        }
    }

    public function hiddenWorkCreate(HiddenWorkRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            $work = HiddenWorksAct::query()->create($request->except(['images']));

            $historyService = new HistoryService('hidden_work_histories');

            $historyService->createHistory(
                guId: $work->id,
                status: $work->status,
                type: LogType::HIDDEN_WORK_CREATE,
                date: null,
                comment: "",
            );

            foreach ($request->images as $image) {
                $path = $image->store('images/hidden-work', 'public');
                $work->images()->create(['url' => $path]);
            }

            DB::commit();


            return $this->sendSuccess(HiddenWorkResource::make($work), 'Hidden work created');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError('Xatolik aniqlandi', $exception->getMessage());
        }
    }

    public function changeHiddenWork()
    {
        try {
            $id = request('id');
            $status = request('status');
            $comment = request('comment') ?? '';
            $historyService = new HistoryService('hidden_work_histories');


            $work = HiddenWorksAct::query()->findOrFail($id);

            if ((int)$status === 5) {

                $work->update([
                    'status' => 5,
                ]);

                $historyService->createHistory(
                    guId: $work->id,
                    status: $work->status,
                    type: LogType::HIDDEN_WORK_REJECT,
                    date: null,
                    comment: $comment,
                );

                return $this->sendSuccess(
                    HiddenWorkResource::make($work->fresh()),
                    'Hidden work updated'
                );
            }

            if ($this->roleId == UserRoleEnum::TEXNIK->value) {

                if ($work->author_answer == 1) {
                    $work->update([
                        'status' => 3,
                        'technic_answer' => 1,
                    ]);

                } else {

                    $work->update([
                        'status' => 1,
                        'technic_answer' => 1,
                    ]);
                }

                $historyService->createHistory(
                    guId: $work->id,
                    status: $work->status,
                    type: LogType::HIDDEN_WORK_ACCEPT,
                    date: null,
                    comment: $comment,
                );
            } elseif ($this->roleId == UserRoleEnum::MUALLIF->value) {

                if ($work->technic_answer == 1) {

                    $work->update([
                        'status' => 3,
                        'author_answer' => 1,
                    ]);

                } else {

                    $work->update([
                        'status' => 1,
                        'author_answer' => 1,
                    ]);
                }

                $historyService->createHistory(
                    guId: $work->id,
                    status: $work->status,
                    type: LogType::HIDDEN_WORK_ACCEPT,
                    date: null,
                    comment: $comment,
                );
            }

            return $this->sendSuccess(HiddenWorkResource::make($work), 'Hidden work updated');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }


    }

    public function hiddenWorkUpdate($id, HiddenWorkRequest $request)
    {
        DB::beginTransaction();
        try {
            $work = HiddenWorksAct::query()->findOrFail($id);
            $work->update($request->validated());

            $historyService = new HistoryService('hidden_work_histories');

            $historyService->createHistory(
                guId: $work->id,
                status: $work->status,
                type: LogType::HIDDEN_WORK_UPDATE,
                date: null,
                comment: "",
            );

            DB::commit();

            return $this->sendSuccess(HiddenWorkResource::make($work), 'Hidden work created');

        } catch (\Exception $exception) {
            DB::rollBack();
            return $this->sendError($exception->getMessage(), $exception->getLine());
        }
    }

    public function hiddenWorkPdf($id)
    {
        try {
            $work = HiddenWorksAct::query()->with(['object', 'histories', 'internal', 'technic', 'author'])->whereId($id)->whereStatus(3)->first();

            $domain = URL::to('/hidden-work-pdf') . '/' . $id;

            $qrImage = base64_encode(QrCode::format('png')->size(200)->generate($domain));

            $pdf = Pdf::loadView('pdf.hide-work', compact(
                'work',
                'qrImage'
            ));

            $pdfOutput = $pdf->output();
            $pdfBase64 = base64_encode($pdfOutput);
            return $this->sendSuccess($pdfBase64, 'PDF');

        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    public function hiddenWorkReport()
    {
        $filters = request()->only(['object_id', 'region_id',  'district_id']);
        return Excel::download(
            new HiddenWorkExport($filters),
            'yopiq_ishlar.xlsx'
        );
    }

    public function hiddenWork($id = null): JsonResponse
    {
        try {
            $items = request()->only('block_id', 'object_id');
            if ($id) {
                $work = HiddenWorksAct::query()->findOrFail($id);
                $resource = HiddenWorkResource::make($work);
            } else {
                $works = HiddenWorksAct::query()
                    ->where('object_id', $items['object_id'])
                    ->when(isset($items['block_id']), function ($query) use ($items) {
                        $query->where('block_id', $items['block_id']);
                    })->get();

                $resource = HiddenWorkResource::collection($works);
            }

            return $this->sendSuccess($resource, 'Hidden work block');
        } catch (\Exception $exception) {
            return $this->sendError($exception->getMessage(), $exception->getCode());
        }
    }

    private function saveFiles($files, $actFile, $geoFile, $act, $foundation)
    {

        $foundationFiles = [];
        $actFiles = [];
        $geoFiles = [];
        $acts = [];
        if ($files) {
            foreach ($files as $file) {
                $path = $file->store('foundation/files', 'public');
                $foundationFiles[] = $path;
            }
        }

        if ($actFile) {
            foreach ($actFile as $file) {
                $path = $file->store('foundation/files', 'public');
                $actFiles[] = $path;
            }
        }

        if ($geoFile) {
            foreach ($geoFile as $file) {
                $path = $file->store('foundation/files', 'public');
                $geoFiles[] = $path;
            }
        }

        if ($act) {
            foreach ($act as $file) {
                $path = $file->store('foundation/files', 'public');
                $acts[] = $path;
            }
        }

        $foundation->update([
            'file' => json_encode($foundationFiles),
            'act_file' => json_encode($actFiles),
            'geo_file' => json_encode($geoFiles),
            'act' => json_encode($acts),
        ]);
    }
}
