<?php

namespace App\Services;

use App\Enums\ObjectStatusEnum;
use App\Enums\UserRoleEnum;
use App\Exceptions\NotFoundException;
use App\Helpers\ClaimStatuses;
use App\Helpers\DeclarationStatus;
use App\Http\Resources\ArticlePalataResource;
use App\Http\Resources\DeclarationBlockResource;
use App\Http\Resources\DifficultyCategoryResource;
use App\Http\Resources\DistrictResource;
use App\Http\Resources\LawyerStatusResource;
use App\Http\Resources\ObjectDesignResource;
use App\Http\Resources\ObjectOrganizationResource;
use App\Http\Resources\RegionResource;
use App\Http\Resources\RegulationStatusResource;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;

class MyGovService
{
    private ArticleRepositoryInterface $articleRepository;
    private UserRepositoryInterface $userRepository;

    public function __construct(
        ArticleRepositoryInterface $articleRepository,
        UserRepositoryInterface    $userRepository,
        protected QuestionService   $questionService,
        protected DeclarationService $declarationService,
    )
    {
        $this->articleRepository = $articleRepository;
        $this->userRepository = $userRepository;
    }

    public function getDxaTaskById(?string $task_id)
    {
        $object = $this->articleRepository->findByTaskId($task_id);

        if (!$object || in_array($object->object_status_id, [ObjectStatusEnum::CANCELED, ObjectStatusEnum::SUBMITTED]))
            return null;

        return [
            'success' => true,
            'object_type' => $object->objectType->name
        ];
    }

    public function getObjectTaskById(int $task_id)
    {
        $object = $this->articleRepository->findByTaskId($task_id);
        if (!$object)
            return null;

        $customer_inn = "";
        $customer_name = "";
        $vendor_inn = "";
        $vendor_name = "";

        foreach ($object->users as $user) {
            if ($user->pivot->role_id == 8) {
                $customer_inn = $user->identification_number;
                $customer_name = $user->organization_name;
            }
            if ($user->pivot->role_id == 10) {
                $vendor_inn = $user->identification_number;
                $vendor_name = $user->organization_name;
            }
        }

        return [
            'request_number' => $task_id,
            'object_name' => $object->name,
            'created_at' => $object->created_at,
            'deadline' => $object->deadline,
            'inn' => $customer_inn,
            'fullname' => $customer_name,
            'vendor_name' => $vendor_name,
            'vendor_inn' => $vendor_inn,
            'status' => $object->object_status_id,
            'gnk_id' => $object->gnk_id,
            'file_urls' => []
        ];
    }

    public function getDeclaration(string $taskId, string $inn): array
    {
        $object = $this->articleRepository->findByTaskId($taskId);

        if (
            !$object ||
            in_array($object->object_status_id, [
                ObjectStatusEnum::SUBMITTED->value,
                ObjectStatusEnum::CANCELED->value
            ])
        ) {
            throw new NotFoundException('Obyekt topilmadi');
        }

        if ($object->funding_source_id == 2){
            throw new NotFoundException('Obyektga bu xizmat turidan ariza yuborish mumkin emas!');
        }

        $declaration = $object->declaration()
            ->where('status', DeclarationStatus::CONFIRMED)
            ->with([
                'factType',
                'unit',
                'doneUnit',
                'blocks'
            ])
            ->first();

        if (!$declaration) {
            throw new NotFoundException('Deklaratsiya topilmadi');
        }

        $claims = $object->govClaims()->whereNotIn('status', [
            ClaimStatuses::TASK_STATUS_REJECTED,
            ClaimStatuses::TASK_STATUS_CONFIRMED,
            ClaimStatuses::TASK_STATUS_ORGANIZATION_REJECTED,
            ClaimStatuses::TASK_STATUS_CANCELLED,
        ])->get();
        if ($claims->isNotEmpty()) {
            throw new NotFoundException('Obyektda jarayondagi arizalar mavjud');
        }

        $user = $object->users()
            ->where('role_id', UserRoleEnum::BUYURTMACHI->value)
            ->first();

        $vendor = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
        $designer = $object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
        $customer = $object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
        $customerConfirmed = Carbon::parse($declaration->customer_confirmed_at);
        $deadline          = Carbon::parse($declaration->object->deadline);
        $objectCreated     = Carbon::parse($declaration->object->created_at);

        if (!$user || $user->pinfl !== $inn) {
            throw new NotFoundException('Buyurtmachi mos kelmadi');
        }

        return [
            'name'                           => $object->name,
            'region_soato'                   => $object->region->soato,
            'region_name'                    => $object->region->name_uz,
            'district_soato'                 => $object->district->soato,
            'district_name'                  => $object->district->name_uz,
            'address'                        => $object->location_building,
            'object_type'                    => $object?->objectType?->name,
            'sphere'                         => $object?->sphere?->name_uz,

            'vendor_tin'                     => $vendor ? $vendor->pinfl : null,
            'vendor_name'                    => $vendor ? $vendor->organization_name : null,
            'designer_tin'                   => $designer ? $designer->pinfl : null,
            'designer_name'                  => $designer ? $designer->organization_name : null,
            'customer_tin'                  => $customer ? $customer->pinfl : null,
            'customer_name'                  => $customer ? $customer->organization_name : null,

            'reestr_number'                  => $object->reestr_number,
            'start_date'                     => $object->created_at,
            'price'                          => $object->construction_cost,

            'base_type'                      => $declaration->factType->name,
            'base_number'                    => $declaration->fact_number,
            'base_date'                      => $declaration->fact_date,

            'declaration_construction_price' => $declaration->construction_price,
            'declaration_instrument_price'   => $declaration->instrument_price,
            'total_real_price'               => $declaration->construction_price + $declaration->instrument_price,

            'fond_construction_price'        => $declaration->fond_construction_price,
            'fond_instrument_price'          => $declaration->fond_instrument_price,
            'total_fond_price'               => $declaration->fond_construction_price + $declaration->fond_instrument_price,

            'note'                           => $declaration->note,
            'capacity'                       => $declaration->capacity,
            'unit'                           => $declaration->unit->name_uz,

            'general_project_info'           => $declaration->general_project_info,
            'general_project_queue'          => $declaration->general_project_queue,
            'process_project_info'           => $declaration->process_project_info,
            'process_project_queue'          => $declaration->process_project_queue,

            'product_production'             => $declaration->product_production,
            'solution_desc'                  => $declaration->solution_desc,
            'labor_protection'               => $declaration->labor_protection,

            'done_work'                      => $declaration->done_work,
            'done_note'                      => $declaration->done_note,
            'blocks'                         => $declaration->blocks ? DeclarationBlockResource::collection($declaration->blocks) : null,
            'in_norm'                        => $objectCreated->diffInDays($deadline),
            'in_real'                        => $customerConfirmed->diffInDays($objectCreated),
            'end_date'                       => Carbon::parse($customerConfirmed)->format('Y-m-d H:i:s'),
            'declaration_pdf'                => URL::to('/declaration-info').'/'.$declaration->id,
        ];
    }

    public function getObjectsByPinfl($pinfl)
    {
        $objects = $this->userRepository->findByPinfl($pinfl);

        if (!$objects)
            return null;

        $responseArr = [];
        $tmpArr[UserRoleEnum::ICHKI->value] = [];
        $tmpArr[UserRoleEnum::TEXNIK->value] = [];
        $tmpArr[UserRoleEnum::MUALLIF->value] = [];

        foreach ($objects->objects()->where('object_status_id', ObjectStatusEnum::PROGRESS)->get() as $object) {
            $tmpArr[$object->getOriginal('pivot_role_id')][] = $object->id;
        }

        $responseArr['description'] = "Bitta xodimga bir vaqtning o‘zida ko‘pi bilan 10 ta  obyekt biriktirilishi mumkin.";
        $responseArr['ichki_nazorat'] = [
            'objects' => count($tmpArr[UserRoleEnum::ICHKI->value]),
//            'can_assign' => !((count($tmpArr[UserRoleEnum::ICHKI->value]) >= 10))
            'can_assign' => true
        ];
        $responseArr['texnik_nazorat'] = [
            'objects' => count($tmpArr[UserRoleEnum::TEXNIK->value]),
//            'can_assign' => !((count($tmpArr[UserRoleEnum::TEXNIK->value]) >= 10))
            'can_assign' => true
        ];
        $responseArr['muallif_nazorat'] = [
            'objects' => count($tmpArr[UserRoleEnum::MUALLIF->value]),
//            'can_assign' => !((count($tmpArr[UserRoleEnum::MUALLIF->value]) >= 10))
            'can_assign' => true
        ];

        return $responseArr;
    }

    public function getObjectsByCustomer($pinfl)
    {
        $objects = $this->userRepository->findByPinfl($pinfl);
        if (!$objects)
            return null;

        $objectsArr = [];
        foreach ($objects->objects()->get() as $object) {
            if ($object->object_status_id == ObjectStatusEnum::PROGRESS) {
                $tmpArr['id'] = $object->id;
                $tmpArr['name'] = $object->name;
                $tmpArr['task_id'] = $object->task_id;
                $objectsArr[] = $tmpArr;
            }
        }
        return $objectsArr;
    }

    public function getObjectsByCadastralNumber($number, $sphereId = null)
    {
        $objects = $this->articleRepository->findByCadastralNumber($number, $sphereId);

        if ($objects->isEmpty()) {
            return null;
        }

        return $objects->map(function ($object) {
            $buyurtmachi = $object->users
                ->firstWhere('pivot.role_id', UserRoleEnum::BUYURTMACHI->value);
            $ichki = $object->users
                ->firstWhere('pivot.role_id', UserRoleEnum::ICHKI->value);

            return [
                'name' => $object->name,
                'task_id' => $object->task_id,
                'region' => RegionResource::make($object->region),
                'district' => DistrictResource::make($object->district),
                'address' => $object->address,
                'closed_at' => $object->closed_at,
                'construction_works' => $object->construction_works,
                'buyurtmachi' => [
                    'organization_name' => $buyurtmachi?->organization_name,
                    'inn' => $buyurtmachi?->identification_number,
                ],
                'ichki_nazorat' => [
                    'fish' => $ichki?->full_name,
                    'phone' => $ichki?->phone,
                ],
                'blocks' => $object->blocks->map(fn($block) => [
                    'name' => $block->name,
                    'floor' => $block->floor,
                ])->values(),
            ];
        })->values();
    }

    public function getObjectList($filters)
    {
        $objects = $this->articleRepository->getList($filters);
        if (!$objects)
            return null;

        $meta = [
            "success" => true,
            "status" => 200,
            "msg" => "Сўровга асосан маълумотлар тўлиқ шакллантирилди",
            "title" => "Обектлар",
            "total_counts" => $objects->total(),
            "description" => [
                'doc_id' => 'Маълумотнинг уникал рақами',
                'app_date' => 'Ариза берилган  сана',
                'app_number' => 'Ариза рақами',
                'obj_name' => 'Объект номи',
                'region_id' => 'Объект ҳудуди коди (СОАТО)',
                'region_name' => 'Объект ҳудуди номи',
                'district_id' => 'Объект тумани (шаҳар) коди (СОАТО)',
                'district_name' => 'Объект тумани (шаҳар) номи',
                'customer_name' => 'Буюртмачи номи',
                'customer_tin' => 'Буюртмачининг СТИРи',
                'builder_name' => 'Пудратчи ташкилот номи',
                'builder_tin' => 'Пудратчи ташкилот СТИРи',
                'rating' => 'Рейтинг кўрсаткичлари',
                'deadline' => 'Қурилиш муддати',
                'finish_date' => 'Объектни фойдаланишга топширилган сана',
                'obj_type' => 'Объект тури',
                'complexity' => 'Мураккаблик тоифаси',
                'build_type' => 'Қурилиш тури',
                'build_cost' => 'Қурилиш қиймати',
                'industry' => 'Қурилиш соҳаси номи',
                'funding' => 'Молиялаштириш манбаи',
            ],
            'data' => ArticlePalataResource::collection($objects->makeHidden(['appends'])),
        ];

        return response()->json($meta, 200);

    }

    public function getObjectOrganization()
    {
        $objects = $this->articleRepository->getList(null);

        if (!$objects)
            return null;

        $response = [
            'objects' => ObjectOrganizationResource::collection($objects),
            'meta' => pagination($objects)
        ];

        return response()->json($response, 200);

    }

    public function getObjectDesign()
    {
        $objects = $this->articleRepository->getList(null);
        if (!$objects)
            return null;

        $response = [
            'objects' => ObjectDesignResource::collection($objects),
            'meta' => pagination($objects)
        ];

        return response()->json($response, 200);
    }

    public function getObjectsRegulations($filters)
    {
        $objects = $this->articleRepository->findByReestr($filters)->get();
        if (!$objects)
            return null;

        $response = $objects->load(['regulations.createdByUser', 'regulations.createdByRole', 'regulations.responsibleUser', 'regulations.responsibleRole'])->map(function ($object) {
            $vendor = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
            return [
                'registration_number' => $object->task_id,
                'registration_date' => $object->created_at,
                'lat' =>$object->lat,
                'long' => $object->long,
                'closed_at' => $object->closed_at,
                'vendor_name' => $vendor ? $vendor->organization_name : null,
                'vendor_tin' => $vendor ? $vendor->pinfl : null,
                'claims' =>  $object?->claims
                    ->where('status', ClaimStatuses::TASK_STATUS_CONFIRMED)
                    ->pluck('guid')
                    ->toArray(),
                'regulations' => $object->regulations->map(function ($regulation) {
                    return [
                        'status' => RegulationStatusResource::make($regulation->regulationStatus),
                        'lawyer_status' => $regulation->lawyerStatus ?  LawyerStatusResource::make($regulation->lawyerStatus) : null,
                        'regulation_number' => $regulation->regulation_number,
                        'from_user' => [
                            'id' => $regulation->createdByUser->id ?? null,
                            'name' => $regulation->createdByUser->name ?? null,
                            'middle_name' => $regulation->createdByUser->middle_name ?? null,
                            'surname' => $regulation->createdByUser->surname ?? null,
                        ],
                        'from_role' => [
                            'id' => $regulation->createdByRole->id ?? null,
                            'name' => $regulation->createdByRole->name ?? null,
                        ],
                        'to_user' =>  [
                            'id' => $regulation->responsibleUser->id ?? null,
                            'name' => $regulation->responsibleUser->name ?? null,
                            'middle_name' => $regulation->responsibleUser->middle_name ?? null,
                            'surname' => $regulation->responsibleUser->surname ?? null,
                        ],
                        'to_role' => [
                            'id' => $regulation->responsibleRole->id ?? null,
                            'name'=> $regulation->responsibleRole->name ?? null,
                        ],
                        'pdf' => $regulation->pdf,
                    ];
                }),
            ];
        });

        return response()->json($response, 200);
    }

    public function getObjectTax($objectId)
    {
        $object = $this->articleRepository->findById($objectId);
        if (!$object) return null;

//        if ($object->funding_source_id != 1 && $object->object_type_id != 1) {
            $customer = $object->users()->where('role_id', UserRoleEnum::BUYURTMACHI->value)->first();
            $builder = $object->users()->where('role_id', UserRoleEnum::QURILISH->value)->first();
            $applicant = $object->users()->where('role_id', UserRoleEnum::ARIZACHI->value)->first();

            $blocks = $object->blocks()->with('type')->get();

            $blockWorkTypes = $blocks->map(function ($block) {
                $works = $this->questionService->getQuestionList(
                    blockId: $block->id,
                    block_type: $block->block_mode_id
                );

                $workTypes = collect($works)
                    ->reject(fn($item) => in_array($item['work_type_id'], [14]))
                    ->flatMap(function ($item) {
                        return collect($item['questions'])
                            ->groupBy('floor')
                            ->flatMap(function ($questions, $floor) use ($item) {
                                $name = $floor ? "$floor - {$item['name']}" : $item['name'];

                                return $questions->contains(fn($q) => $q['work_type_status']->value == 2)
                                    ? [['name' => $name]]
                                    : [];
                            });
                    })
                    ->values()
                    ->toArray();

                return [
                    'id' => $block->id,
                    'name' => $block->name,
                    'floor' => $block->floor,
                    'construction_area' => $block->construction_area,
                    'count_apartments' => $block->count_apartments,
                    'work_types' => $workTypes,
                ];
            });

            $data = [
                'object_id' => $object->id,
                'object_name' => $object->name,
                'sphere_id' => $object->sphere ? $object->sphere->id : null,
                'sphere_name' => $object->sphere ? $object->sphere->name_uz : null,
                'created_at' => date_format($object->created_at, 'Y-m-d H:i:s'),
                'closed_at' => $object->closed_at,
                'customer_name' => $customer?->organization_name ?? '',
                'applicant_name' => $applicant?->organization_name ?? '',
                'builder_name' => $builder?->organization_name ?? '',
                'cadastral_number' => $object->cadastral_number,
                'deadline' => $object->deadline,
                'difficulty_category' => $object->difficulty ? DifficultyCategoryResource::make($object->difficulty) : null,
                'construction_works' => $object->construction_works,
                'region' => $object->region ? $object->region->only(['name_uz', 'soato']) : null,
                'district' => $object->district ? $object->district->only(['name_uz', 'soato']) : null,
                'object_type' => $object->objectType ? $object->objectType->only(['id','name']) : null,
                'address' => $object->location_building,
                'number_protocol' => $object->number_protocol,
                'status_id' =>  $object->object_status_id,
                'status_name' => $object->objectStatus->name,
                'reestr_number' => $object->reestr_number,
                'funding_source' => $object->fundingSource ? $object->fundingSource->only(['id', 'description']) : null,
                'construction_cost' => $object->construction_cost,
                'pinfl_customer' => $customer?->name ? $customer->pinfl : '',
                'tin_customer' => $customer?->name ? '' : ($customer?->pinfl ?? ''),
                'tin_applicant' => $applicant?->pinfl ?? '',
                'tin_general_contractor' => $builder?->pinfl ?? '',
                'blocks' => $blockWorkTypes,
            ];

            return $data;
//        }

//        return null;
    }

    public function getObjectByReestr($filters)
    {
        $objects = $this->articleRepository->findByReestr($filters)->get();
        return $objects->map(function ($object) {
            $designer = $object->users()->where('role_id', UserRoleEnum::LOYIHA->value)->first();
            return  [
                'id' => $object->id,
                'name' => $object->name,
                'created_at' => date_format($object->created_at, 'Y-m-d H:i:s'),
                'closed_at' => $object->closed_at,
                'deadline' => $object->deadline,
                'reestr_number' => $object->reestr_number,
                'status_id' =>  $object->object_status_id->value,
                'status_name' => $object->objectStatus->name,
                'designer_inn' => $designer?->pinfl ?? '',
                'designer_name' => $designer?->organization_name ?? '',
                'construction_work' => $object->construction_works,
                'difficulty_category' => $object->difficulty ? DifficultyCategoryResource::make($object->difficulty) : null,
            ];
        });
    }


}
