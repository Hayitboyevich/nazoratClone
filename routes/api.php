<?php

use App\Http\Controllers\Api\ApiController;
use App\Http\Controllers\Api\BlockController;
use App\Http\Controllers\Api\ChecklistAnswerController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\ClaimGovController;
use App\Http\Controllers\Api\ConcreteWorkController;
use App\Http\Controllers\Api\IllegalObjectController;
use App\Http\Controllers\Api\LabController;
use App\Http\Controllers\Api\LabMetrologyController;
use App\Http\Controllers\Api\LabTestContractController;
use App\Http\Controllers\Api\LabTestController;
use App\Http\Controllers\Api\MyGovController;
use App\Http\Controllers\Api\OgohAppealController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\QualityControlController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\RegulationController;
use App\Http\Controllers\Api\ResponseController;
use App\Http\Controllers\Api\SertInfoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserTypeController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\ViolationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RegisterController;
use App\Http\Controllers\Api\ObjectController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\MonitoringController;
use App\Http\Controllers\Api\InformationController;
use App\Http\Controllers\Api\SphereController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\VersionController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\PresentationController;
use App\Http\Controllers\Api\DeclarationController;
use App\Http\Controllers\Api\TechnicalStaffController;
use App\Http\Controllers\Api\ConstructionQualityController;
use App\Http\Controllers\Api\AiControlController;
use App\Http\Controllers\Api\StatisticsController;


Route::post('login', [LoginController::class, 'login']);
Route::post('auth', [LoginController::class, 'auth']);
Route::post('challenge', [LoginController::class, 'challenge']);
Route::post('check-role', [LoginController::class, 'checkRole']);
Route::post('login-usb', [LoginController::class, 'loginUsb']);
Route::post('check-user', [InformationController::class, 'checkUser']);
Route::post('shaffofid', [InformationController::class, 'getShaffofId']);
Route::post('check-document', [InformationController::class, 'checkDocument']);
Route::post('shaffof-logout', [InformationController::class, 'shaffofLogout']);
Route::get('object-count-layout', [InformationController::class, 'objectCount']);
Route::get('objects-by-pinfl', [InformationController::class, 'userObjects']);
Route::get('regulation-excel', [InformationController::class, 'regulationExcel']);
Route::get('version/{type}', [VersionController::class, 'index']);
Route::post('version-update', [VersionController::class, 'update']);
Route::get('conclusion/{taskId}/download', [InformationController::class, 'conclusionDxaPDF']);
Route::get('expertise/{taskId}/download', [InformationController::class, 'getExpertiseFile']);
Route::get('claim/conclusion/{taskId}/download', [InformationController::class, 'conclusionClaimPDF']);


Route::get('{module}/{api}/responses', [ResponseController::class, 'receive']);

Route::get('pdf-generation', [PdfController::class, 'generation']);
Route::get('pdf-dalolatnoma/{id}', [PdfController::class, 'getDalolatnoma']);
Route::get('pdf-presentation/{id}', [PdfController::class, 'presentationPdf']);
Route::get('pdf-claim/{id}', [PdfController::class, 'pdfClaim']);
Route::get('pdf-declaration/{id}', [PdfController::class, 'pdfDeclaration']);
Route::get('register-oferta/{id}', [PdfController::class, 'oferta']);
Route::get('organization-pdf/{id}', [PdfController::class, 'pdfOrganization']);
Route::get('lab-contract', [ApiController::class, 'getLabContract']);
Route::get('organization-pdf/{id}/download', [PdfController::class, 'pdfOrganizationDownload']);
Route::get('get-scheme-pdf/{main_info_id}', [LabController::class, 'getSchemePdf']);
Route::get('get-statement-pdf/{statement_id}', [LabController::class, 'getStatementPdf']);
Route::get('get-object-pdf/{taskId}', [PdfController::class, 'getObjectPdf'])->name('object-pdf');
Route::get('statement', [InformationController::class, 'statement']);
Route::get('monitoring-gnk', [InformationController::class, 'monitoringGNK']);
Route::get('reestr', [InformationController::class, 'reestr']);
Route::get('expertise-files', [InformationController::class, 'expertiseFiles']);
Route::get('get-object/{id}', [ObjectController::class, 'getObjectById']);
Route::get('time', [InformationController::class, 'time']);
Route::get('qr-image/{id}', [InformationController::class, 'qrImage']);

Route::group([
    'middleware' => ['role_check', 'auth:api', 'refresh_token']
], function () {
    Route::get('profile', [ProfileController::class, 'profile']);
    Route::post('profile-edit', [ProfileController::class, 'edit']);
    Route::post('logout', [LoginController::class, 'logout']);
    Route::post('refresh', [LoginController::class, 'refresh']);
    Route::get('user-type', [UserTypeController::class, 'index']);
    Route::post('user-type/create', [UserTypeController::class, 'create']);
    Route::post('user-type/edit/{id}', [UserTypeController::class, 'edit']);

    Route::get('monitoring-objects', [InformationController::class, 'monitoringObjects']);
    Route::get('cadastral-info', [InformationController::class, 'cadastr']);
    Route::get('monitoring-list', [MonitoringController::class, 'monitoringList']);
    Route::get('monitoring-customer', [InformationController::class, 'monitoringCustomer']);
    Route::get('rating', [InformationController::class, 'rating']);
    Route::get('conference', [InformationController::class, 'conference']);
    Route::get('tender', [InformationController::class, 'tender']);
    Route::get('get-pdf-repo', [InformationController::class, 'getRepoPDF']);
    Route::get('normative-documents', [InformationController::class, 'normativeDocs']);
    Route::get('topics', [InformationController::class, 'topics']);
    Route::get('basis', [InformationController::class, 'basis']);
    Route::get('notifications', [InformationController::class, 'notifications']);
    Route::get('notification-count', [InformationController::class, 'notificationCount']);
    Route::post('notification-read', [InformationController::class, 'notificationRead']);
    Route::get('organization', [InformationController::class, 'organization']);

    Route::get('statistics', [StatisticsController::class, 'statistics']);
    Route::get('reports', [StatisticsController::class, 'reports']);
    Route::get('regulation-report', [StatisticsController::class, 'regulationReport']);
    Route::get('claim-statistic', [StatisticsController::class, 'claimStatistic']);
    Route::get('register-list', [StatisticsController::class, 'registerList']);
    Route::get('excelClaim', [StatisticsController::class, 'excel']);
    Route::get('excelTaskClaim', [StatisticsController::class, 'excelTask']);
    Route::get('account-report', [StatisticsController::class, 'accountReport']);
    Route::get('testClaim', [StatisticsController::class, 'testClaim']);
    Route::get('get-claim-org-answer', [StatisticsController::class, 'orgAnswer']);

    Route::get('sphere', [SphereController::class, 'spheres']);
    Route::get('programs', [ProgramController::class, 'programs']);



    Route::get('regions', [RegionController::class, 'regions']);
    Route::get('districts/{id}', [RegionController::class, 'districts']);

    Route::get('permissions', [PermissionController::class, 'permissions']);
    Route::get('roles', [PermissionController::class, 'roles']);
    Route::post('roles/create', [PermissionController::class, 'create']);

    Route::get('users', [UserController::class, 'users']);
    Route::get('check-certificate', [UserController::class, 'checkCertificate']);
    Route::get('inspectors', [UserController::class, 'getInspector']);
    Route::post('users/create', [UserController::class, 'create']);
    Route::get('users/status', [UserController::class, 'status']);
    Route::get('users/edit/{id}', [UserController::class, 'edit']);
    Route::post('passport-info', [UserController::class, 'getPassportInfo']);
    Route::get('employees', [UserController::class, 'getEmployees']);
    Route::get('users-count', [UserController::class, 'count']);
    Route::post('users-change', [UserController::class, 'userChange']);
    Route::get('users-change-list', [UserController::class, 'userChangeList']);
    Route::post('accept-user-change', [UserController::class, 'acceptUserChange']);
    Route::post('reject-user-change', [UserController::class, 'rejectUserChange']);
    Route::post('user-delete', [UserController::class, 'delete']);
    Route::post('inspector-location', [UserController::class, 'inspectorLocation']);
    Route::post('inspector-start-work', [UserController::class, 'inspectorStartWork']);
    Route::post('get-inspector-location/{id}', [UserController::class, 'getInspectorLocation']);
    Route::get('user-info', [UserController::class, 'getUserInfo']);
    Route::get('technic-statistic/{id}', [UserController::class, 'technicStatistic']);
    Route::get('technics', [UserController::class, 'technics']);
    Route::get('user-expertise', [UserController::class, 'getUserExpertise']);
    Route::get('user-gov-expertise', [UserController::class, 'getUserGovExpertise']);

    Route::get('technical-staff/{id}', [TechnicalStaffController::class, 'index']);
    Route::post('technical-staff-create', [TechnicalStaffController::class, 'create']);
    Route::post('technical-staff-update/{id}', [TechnicalStaffController::class, 'update']);

    Route::get('quality-assessment/{type}/object/{id}', [ConstructionQualityController::class, 'index']);
    Route::get('quality-assessment/report', [ConstructionQualityController::class, 'report']);
    Route::post('quality-assessment-create', [ConstructionQualityController::class, 'create']);


    Route::get('registers', [RegisterController::class, 'registers']);
    Route::get('check-source/{id}', [RegisterController::class, 'checkFundingSource']);
    Route::get('re-registers', [RegisterController::class, 'reRegister']);
    Route::get('register-count', [RegisterController::class, 'registerCount']);
    Route::get('re-register-count', [RegisterController::class, 'reRegisterCount']);
    Route::get('register/{id}', [RegisterController::class, 'getRegister']);
    Route::post('register/edit', [RegisterController::class, 'edit']);
    Route::get('register-status', [RegisterController::class, 'status']);
    Route::get('register-get-pdf', [RegisterController::class, 'getPDF']);
    Route::get('total-count', [RegisterController::class, 'totalCount']);
    Route::get('register-lawyer-count', [RegisterController::class, 'lawyerCount']);
    Route::get('register-excell/{type}', [RegisterController::class, 'getRegisterExcel']);
    Route::get('register-oferta/{id}', [RegisterController::class, 'oferta']);
    Route::get('register-history/{id}', [RegisterController::class, 'history']);

    Route::group(['prefix' => 'sert'], function () {
        Route::post('sert-get-user-info', [SertInfoController::class, 'getUserByPinf']);
    });

    Route::get('ai-control/{id?}', [AiControlController::class, 'index']);
    Route::post('ai-control-change', [AiControlController::class, 'change']);


    Route::post('send-inspector', [RegisterController::class, 'sendInspector']);
    Route::get('expertise-blocks/{id}', [RegisterController::class, 'getExpertiseBlocks']);
    Route::post('reject-inspection/{id}', [RegisterController::class, 'rejectInspection']);
    Route::post('send-inspection/{id}', [RegisterController::class, 'sendInspection']);
    Route::get('check-file-invest/{id}', [RegisterController::class, 'checkFileInvest']);
    Route::post('send-register', [RegisterController::class, 'sendRegister']);
    Route::post('send-update', [RegisterController::class, 'sendUpdate']);
    Route::post('reject-register', [RegisterController::class, 'rejectRegister']);
    Route::post('send-court-register', [RegisterController::class, 'sendCourt']);
    Route::post('create-fine-response', [RegisterController::class, 'fine']);


    Route::get('objects', [ObjectController::class, 'index']);
    Route::get('object-stream/{id}', [ObjectController::class, 'objectStream']);
    Route::get('object-camera/{id}', [ObjectController::class, 'objectCamera']);
    Route::get('object/{id}', [ObjectController::class, 'getObject']);
    Route::get('object-location-list', [ObjectController::class, 'locationList']);
    Route::get('object-maps', [ObjectController::class, 'objectLocationList']);
    Route::get('object-count-maps', [ObjectController::class, 'objectCountList']);
    Route::get('object-deadline-log', [ObjectController::class, 'deadlineLog']);
    Route::get('object-histories/{id}', [ObjectController::class, 'getHistory']);
    Route::post('update-object-sphere', [ObjectController::class, 'updateSphere']);
    Route::get('object-images/{id}', [ObjectController::class, 'getObjectImages']);
    Route::get('object-by-task/{task_id}', [ObjectController::class, 'getObjectByTaskId']);
    Route::get('object-count', [ObjectController::class, 'objectCount']);
    Route::get('get-object', [ObjectController::class, 'objectByParams']);
    Route::get('user-objects', [ObjectController::class, 'userObjects']);
    Route::post('object-create-manual', [ObjectController::class, 'manualCreate']);
    Route::post('object-create-register', [ObjectController::class, 'objectCreate']);
    Route::post('object-store-elastic', [ObjectController::class, 'objectElastic']);
    Route::post('object-update-manual', [ObjectController::class, 'manualUpdate']);
    Route::get('funding-sources', [ObjectController::class, 'fundingSource']);
    Route::post('object-create', [ObjectController::class, 'create']);
    Route::post('check-object', [ObjectController::class, 'checkObject']);
    Route::post('change-object-status', [ObjectController::class, 'changeObjectStatus']);
    Route::post('change-object-location', [ObjectController::class, 'changeObjectLocation']);
    Route::post('delete-object-payment', [ObjectController::class, 'deleteObjectPayment']);
    Route::post('object-deadline-change', [ObjectController::class, 'deadlineChange']);
    Route::post('payment', [ObjectController::class, 'payment']);
    Route::post('payment-laboratory', [ObjectController::class, 'paymentLaboratory']);
    Route::get('total-payment', [ObjectController::class, 'totalPayment']);
    Route::get('payment-statistics', [ObjectController::class, 'paymentStatistics']);
    Route::get('accountant-objects', [ObjectController::class, 'accountObjects']);
    Route::get('accountant-report', [ObjectController::class, 'accountReport']);
    Route::post('rotation', [ObjectController::class, 'rotation']);
    Route::post('object-create-user', [ObjectController::class, 'objectCreateUser']);
    Route::post('inspector-attachment-object', [ObjectController::class, 'inspectorAttachmentObject']);
    Route::post('inspector-change-object', [ObjectController::class, 'inspectorChangeObject']);
    Route::post('object-change-price', [ObjectController::class, 'objectChangePrice']);
    Route::post('object-laboratory-price', [ObjectController::class, 'objectLaboratoryPrice']);
    Route::post('object-change-funding', [ObjectController::class, 'objectChangeFunding']);


    Route::get('blocks/{id}', [BlockController::class, 'index']);
    Route::get('block/{id}', [BlockController::class, 'getBlock']);
    Route::get('response-blocks/{id}', [BlockController::class, 'responseBlock']);
    Route::get('block-modes', [BlockController::class, 'blockModes']);
    Route::get('block-types', [BlockController::class, 'blockTypes']);
    Route::post('block-create', [BlockController::class, 'create']);
    Route::post('block-delete/{id}', [BlockController::class, 'delete']);
    Route::post('block-edit', [BlockController::class, 'edit']);
    Route::post('block-add-mode', [BlockController::class, 'addModeToBlock']);
    Route::post('foundation-create', [BlockController::class, 'foundationCreate']);
    Route::get('foundation', [BlockController::class, 'foundationBlock']);
    Route::post('block-checklist/{id}', [BlockController::class, 'blockChecklist']);
    Route::post('hidden-work-create', [BlockController::class, 'hiddenWorkCreate']);
    Route::post('hidden-work-change', [BlockController::class, 'changeHiddenWork']);
    Route::get('hidden-work/{id?}', [BlockController::class, 'hiddenWork']);
    Route::get('hidden-work-pdf/{id?}', [BlockController::class, 'hiddenWorkPdf']);
    Route::get('hidden-work-report', [BlockController::class, 'hiddenWorkReport']);
    Route::post('hidden-work-update/{id}', [BlockController::class, 'hiddenWorkUpdate']);

    Route::post('send-answer', [QuestionController::class, 'sendAnswer']);
    Route::get('levels', [QuestionController::class, 'levels']);
    Route::post('create-regulation', [QuestionController::class, 'createRegulation']);

    Route::get('monitoring', [MonitoringController::class, 'monitoring']);
    Route::post('monitoring-create', [MonitoringController::class, 'create']);

    Route::get('regulations', [RegulationController::class, 'regulations']);
    Route::get('regulation/{id}', [RegulationController::class, 'getRegulation']);
    Route::post('accept-answer', [RegulationController::class, 'acceptAnswer']);
    Route::post('accept-deed', [RegulationController::class, 'acceptDeed']);
    Route::post('accept-deed-cmr', [RegulationController::class, 'acceptDeedCmr']);
    Route::post('accept-date', [RegulationController::class, 'acceptDate']);
    Route::post('send-deed', [RegulationController::class, 'sendDeed']);
    Route::post('ask-date', [RegulationController::class, 'askDate']);
    Route::post('reject-date', [RegulationController::class, 'rejectDate']);
    Route::post('reject-deed', [RegulationController::class, 'rejectDeed']);
    Route::post('reject-deed-cmr', [RegulationController::class, 'rejectDeedCmr']);
    Route::post('reject-answer', [RegulationController::class, 'rejectAnswer']);
    Route::post('create-fine', [RegulationController::class, 'fine']);
    Route::post('send-court', [RegulationController::class, 'sendCourt']);
    Route::get('regulation-lawyer-count', [RegulationController::class, 'lawyerCount']);
    Route::get('regulation-lawyer-export', [RegulationController::class, 'lawyerRegulationExport']);
    Route::get('regulation-count', [RegulationController::class, 'regulationCount']);
    Route::get('author-regulation', [RegulationController::class, 'getAuthorRegulations']);
    Route::post('author-regulation-create', [RegulationController::class, 'createRegulation']);
    Route::post('send-answer-author-regulation', [RegulationController::class, 'sendAnswerAuthorRegulation']);
    Route::get('author-regulation-report', [RegulationController::class, 'authorReport']);
    Route::post('regulation-change', [RegulationController::class, 'regulationChange']);

    Route::get('act-violations', [ViolationController::class, 'actViolations']);
    Route::get('violations', [ViolationController::class, 'violations']);

    Route::get('checklist', [MonitoringController::class, 'getChecklist']);
    Route::get('checklist-log', [MonitoringController::class, 'getChecklistLog']);
    Route::get('checklist-log-file', [MonitoringController::class, 'getChecklistLogFile']);
    Route::get('checklist-answer', [MonitoringController::class, 'getChecklistAnswer']);
    Route::get('checklist-regular', [MonitoringController::class, 'getChecklistRegular']);
    Route::post('checklist-file-send', [MonitoringController::class, 'sendCheckListFile']);
    Route::post('accept-work-type', [MonitoringController::class, 'acceptWorkType']);
    Route::post('check-block/{id}', [MonitoringController::class, 'checkBlock']);

    Route::get('check-list-answer', [CheckListAnswerController::class, 'index']);
    Route::post('check-list-status-change', [CheckListAnswerController::class, 'checklistStatusChange']);

    Route::get('holidays', [HolidayController::class, 'index']);
    Route::post('holiday/create', [HolidayController::class, 'create']);

    Route::group(['prefix' => 'presentation'], function () {
        Route::get('/material', [PresentationController::class, 'material']);
        Route::post('/create', [PresentationController::class, 'create']);
        Route::post('/reject/{id}', [PresentationController::class, 'reject']);
        Route::get('/history/{id}', [PresentationController::class, 'history']);
        Route::post('/accept/{id}', [PresentationController::class, 'accept']);
        Route::post('/send-inspector', [PresentationController::class, 'sendInspector']);
        Route::get('/report', [PresentationController::class, 'report']);
        Route::get('/pdf/{id}', [PresentationController::class, 'pdf']);
        Route::get('/pdf-deed/{id}', [PresentationController::class, 'pdfDeed']);
        Route::get('/count', [PresentationController::class, 'count']);
        Route::get('/{id?}', [PresentationController::class, 'index']);

    });

    Route::group(['prefix' => 'declaration'], function () {
        Route::post('/create', [DeclarationController::class, 'create']);
        Route::get('/unit', [DeclarationController::class, 'unit']);
        Route::post('/accept/{id}', [DeclarationController::class, 'accept']);
        Route::post('/consumer-change', [DeclarationController::class, 'consumerChange']);
        Route::post('/reject/{id}', [DeclarationController::class, 'reject']);
        Route::post('/update/{id}', [DeclarationController::class, 'update']);
        Route::get('/fact-types', [DeclarationController::class, 'factTypes']);
        Route::get('/pdf/{id}', [DeclarationController::class, 'pdf']);
        Route::get('/history/{id}', [DeclarationController::class, 'history']);
        Route::get('/object/{id}', [DeclarationController::class, 'object']);
        Route::post('/conclusion/create', [DeclarationController::class, 'conclusionCreate']);
        Route::get('/conclusion', [DeclarationController::class, 'conclusion']);
        Route::get('/conclusion/pdf/{id}', [DeclarationController::class, 'conclusionPdf']);
        Route::get('/{id?}', [DeclarationController::class, 'index']);
    });

    Route::group(['prefix' => 'ogoh-appeal'], function () {
        Route::post('answer', [OgohAppealController::class, 'answer']);
        Route::post('message', [OgohAppealController::class, 'message']);
        Route::get('/{id?}', [OgohAppealController::class, 'index'])->whereNumber('id');
    });

    Route::group(['prefix' => 'claimgov'], function () {
        Route::get('sphere/{id?}', [ClaimgovController::class, 'sphere']);
        Route::post('attach-sphere', [ClaimgovController::class, 'attachSphere']);
        Route::post('conclusion-organization', [ClaimgovController::class, 'conclusionOrganization']);
        Route::post('conclusion-inspector', [ClaimgovController::class, 'conclusionInspector']);
        Route::post('conclusion-director', [ClaimgovController::class, 'conclusionDirector']);
        Route::post('reject', [ClaimgovController::class, 'reject']);
        Route::get('histories/{id}', [ClaimgovController::class, 'histories']);
        Route::get('pdf/{id}', [ClaimgovController::class, 'pdf']);
        Route::get('author-pdf/{id}', [ClaimgovController::class, 'authorPdf']);
        Route::get('technic-pdf/{id}', [ClaimgovController::class, 'technicPdf']);
        Route::get('get-repo/{task_id}', [ClaimgovController::class, 'getRepoPDF']);
        Route::get('mygov-pdf/{id}', [ClaimgovController::class, 'myGovPdf']);
        Route::get('organization-statistics', [ClaimgovController::class, 'organizationStatistics']);
        Route::get('old-reviews', [ClaimgovController::class, 'oldReviews']);
        Route::get('count', [ClaimgovController::class, 'count']);
        Route::get('organization-pdf/{review_id}/download', [ClaimgovController::class, 'organizationPDF']);
        Route::get('/{id?}', [ClaimgovController::class, 'index'])->whereNumber('id');;
        Route::get('statistics', [ClaimController::class, 'statistics']);
        Route::get('get-conclusion-pdf', [ClaimgovController::class, 'getConclusionPDF']);
    });


    Route::post('test', [RegulationController::class, 'test']);


/////////////////////////////////////Lab apis//////////////////////////////////////////////////////////////////////////////////

    Route::get('get-eks-info', [LabController::class, 'getEksInfo'])->middleware('permission:get_eks_info');
    Route::post('create-lab-claim', [LabController::class, 'createClaim'])->middleware('permission:create_lab_claim');
    Route::get('get-lab-claims-list', [LabController::class, 'getListLabClaims'])->middleware('permission:get_lab_list');
    Route::get('get-lab-claim/{id}', [LabController::class, 'getLabClaim'])->middleware('permission:get_lab_claim');
    Route::get('get-organizations', [LabController::class, 'getOrganizations'])->middleware('permission:get_orgs');
    Route::get('lab-statistics', [LabController::class, 'labClaimStatistics'])->middleware('permission:get_statistics');
    Route::post('update-lab-claim/{id}', [LabController::class, 'updateLabClaim'])->middleware('permission:update_lab_claim');
    Route::post('customer-contract-action', [LabController::class, 'customerContractAction'])->middleware('permission:customer_con_action');
    Route::post('create-test', [LabController::class, 'createTest'])->middleware('permission:create_lab_test');
    Route::get('get-test-categories', [LabController::class, 'getTestCategories'])->middleware('permission:get_lab_tests');
    Route::post('set-price', [LabController::class, 'setPrice'])->middleware('permission:set_lab_test_price');
    Route::post('edit-test-category', [LabController::class, 'editTestCategory'])->middleware('permission:edit_lab_test');
    Route::post('delete-test-category', [LabController::class, 'deleteTestCategory'])->middleware('permission:delete_lab_test');
    Route::get('get-test-with-price/{id}', [LabController::class, 'getTestWithPrice'])->middleware('permission:get_priced_test');
    Route::get('get-lab-contract/{id}', [LabController::class, 'getLabContract'])->middleware('permission:get_lab_con');

    Route::post('create-lab-claim-manually',[LabController::class, 'createLabClaimManually'])->name('createLabClaimManually')->middleware('permission:create_lab_claim_manually');
    Route::get('get-lab-work-types', [LabController::class, 'getLabWorkTypes']);
    Route::post('save-manually-lab-contract/{claim_id}', [LabController::class, 'saveManuallyLabContract'])->name('save_manually_lab_contract')->middleware('permission:save_manually_lab_contract');

    Route::post('create-gradirovka/{object_id}/{block_id}', [LabController::class, 'createLabGradirovka']);
    Route::post('pass-lab-test/{main_grad_id}', [LabController::class, 'passLabTest']);
    Route::get('get-lab-grad-info/{object_id}/{block_id}', [LabController::class, 'getLabGradInfo']);
    Route::get('get-lab-measurement-instruments', [LabController::class, 'getLabMeasurements']);
    Route::post('create-protocol/{lab_grad_id}', [LabController::class, 'crateProtocol']);
    Route::get('generate-protocol-excel/{lab_grad_id}', [LabController::class, 'downloadProtocol']);

//////////////////////Actions with articles//////////////////////////////

    Route::post('attach-laborant', [LabController::class, 'attachLaborantToObject']);
    Route::post('attach-object', [LabController::class, 'attachObject'])->middleware('permission:attach_object');
    Route::post('lab-checklist-answer', [LabController::class, 'checkListAnswer']);
    Route::post('lab-probation-create', [LabController::class, 'probationCreate']);
    Route::get('lab-checklist-notification', [LabController::class, 'checkListAnswersNotification']);
    Route::get('lab-checklist-answers-info/{id}', [LabController::class, 'checkListAnswersInfo']);
    Route::get('lab-users', [LabController::class, 'getLabUsers']);
    Route::get('all-objects-list', [LabController::class, 'allObjectsList'])->middleware('permission:get_all_objects_list');

    Route::get('lab-check-list-histories', [LabController::class, 'labCheckListHistories']);
    Route::group(['prefix' => 'lab-metrology'], function () {
        Route::get('dictionaries', [LabMetrologyController::class, 'dictionaries']);
        Route::get('statistics', [LabMetrologyController::class, 'statistics']);
        Route::get('instruments/{id?}', [LabMetrologyController::class, 'instruments'])->whereNumber('id');
        Route::post('instruments/create', [LabMetrologyController::class, 'instrumentCreate']);
        Route::post('instruments/update/{id}', [LabMetrologyController::class, 'instrumentUpdate']);
        Route::post('instruments/status/{id}', [LabMetrologyController::class, 'instrumentStatus']);
        Route::post('instruments/delete/{id}', [LabMetrologyController::class, 'instrumentDelete']);
        Route::get('instruments/{id}/history', [LabMetrologyController::class, 'instrumentHistory'])->whereNumber('id');
        Route::get('certificates/{id}/history', [LabMetrologyController::class, 'certificateHistory'])->whereNumber('id');
        Route::get('certificates', [LabMetrologyController::class, 'certificates']);
        Route::post('certificates/create', [LabMetrologyController::class, 'certificateCreate']);
        Route::post('certificates/update/{id}', [LabMetrologyController::class, 'certificateUpdate']);
        Route::post('certificates/status/{id}', [LabMetrologyController::class, 'certificateStatus']);
        Route::post('certificates/delete/{id}', [LabMetrologyController::class, 'certificateDelete']);
        Route::get('standards/{id?}', [LabMetrologyController::class, 'standards'])->whereNumber('id');
    });
    Route::group(['prefix' => 'lab-test-contracts'], function () {
        Route::get('dictionaries', [LabTestContractController::class, 'dictionaries']);
        Route::get('statistics', [LabTestContractController::class, 'statistics']);
        Route::get('employees', [LabTestContractController::class, 'employees']);
        Route::get('my-items', [LabTestContractController::class, 'myItems']);
        Route::get('objects', [LabTestContractController::class, 'objects']);
        Route::get('{id?}', [LabTestContractController::class, 'contracts'])->whereNumber('id');
        Route::get('{id}/history', [LabTestContractController::class, 'history'])->whereNumber('id');
        Route::get('pdf/{id}', [LabTestContractController::class, 'pdf'])->whereNumber('id');
        Route::post('create', [LabTestContractController::class, 'create']);
        Route::post('create-additional/{id}', [LabTestContractController::class, 'createAdditional'])->whereNumber('id');
        Route::post('update/{id}', [LabTestContractController::class, 'update'])->whereNumber('id');
        Route::post('blocks/{id}', [LabTestContractController::class, 'saveBlocks'])->whereNumber('id');
        Route::post('items/{id}', [LabTestContractController::class, 'saveItems'])->whereNumber('id');
        Route::post('send/{id}', [LabTestContractController::class, 'send'])->whereNumber('id');
        Route::post('approve/{id}', [LabTestContractController::class, 'approve'])->whereNumber('id');
        Route::post('return/{id}', [LabTestContractController::class, 'returnToEmployee'])->whereNumber('id');
        Route::post('cancel/{id}', [LabTestContractController::class, 'cancel'])->whereNumber('id');
        Route::post('attach-object/{id}', [LabTestContractController::class, 'attachObject'])->whereNumber('id');
        Route::post('delete/{id}', [LabTestContractController::class, 'delete'])->whereNumber('id');
    });
    Route::group(['prefix' => 'lab-tests'], function () {
        Route::get('dictionaries', [LabTestController::class, 'dictionaries']);
        Route::get('{id?}', [LabTestController::class, 'tests'])->whereNumber('id');
        Route::get('{id}/history', [LabTestController::class, 'history'])->whereNumber('id');
        Route::get('pdf/{id}', [LabTestController::class, 'pdf'])->whereNumber('id');
        Route::post('create', [LabTestController::class, 'create']);
        Route::post('update/{id}', [LabTestController::class, 'update'])->whereNumber('id');
        Route::post('form-protocol/{id}', [LabTestController::class, 'formProtocol'])->whereNumber('id');
        Route::post('review/{id}', [LabTestController::class, 'review'])->whereNumber('id');
        Route::post('delete/{id}', [LabTestController::class, 'delete'])->whereNumber('id');
    });
    Route::group(['prefix' => 'lab-check-list'], function () {
        Route::get('testing-types', [LabController::class, 'labCheckListTestingTypes']);
        Route::post('save-main-info', [LabController::class, 'saveMainInfo']);
        Route::post('save-testing-con-info', [LabController::class, 'saveTestingConInfo']);
        Route::get('get-created-tests', [LabController::class, 'getBlockTests']);
        Route::get('get-question-marks', [LabController::class, 'getQuestionMarks']);
        Route::post('save-statement', [LabController::class, 'saveStatement']);
        Route::post('save-first-chosen-test-type', [LabController::class, 'saveFirstChosenTestType'])->name('save_first_chosen_test_type')->middleware('permission:save_first_chosen_test_type');
        Route::post('save-question-conditions', [LabController::class, 'saveQuestionConditions']);
        Route::get('get-first-chosen-testing-type', [LabController::class, 'getFirstChosenTestingType']);
        Route::get('get-tests-conditions', [LabController::class, 'getTestsConditions']);
        Route::get('get-work-type-average-marks',[LabController::class,'getWorkTypeAverageMarks']);
        Route::get('get-statement/{statement_id}',[LabController::class,'getStatement']);
        Route::get('get-schmidt-ready-tests', [LabController::class, 'getSchmidtReadyTests']);
        Route::post('save-statement-marks', [LabController::class, 'saveStatementMarks']);
//        Route::get('get-scheme-pdf/{main_info_id}', [LabController::class, 'getSchemePdf']);
//        Route::get('get-statement-pdf/{statement_id}', [LabController::class, 'getStatementPdf']);
        Route::post('answer-reg-lab', [LabController::class, 'answerRegLabel']);
        Route::get('get-statements',[LabController::class, 'getStatements']);
        Route::get('get-object-info',[LabController::class, 'getObjectInfo']);
    });

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    Route::group(['prefix' => 'illegal'], function () {
        Route::get('objects', [IllegalObjectController::class, 'objectsList']);
        Route::get('statistics', [IllegalObjectController::class, 'getStatistics']);
        Route::get('questions/{id}', [IllegalObjectController::class, 'questionList']);
        Route::get('object/{id}', [IllegalObjectController::class, 'getObject']);
        Route::get('districts', [IllegalObjectController::class, 'districtList']);
        Route::post('create-object', [IllegalObjectController::class, 'createObject']);
        Route::post('update-object', [IllegalObjectController::class, 'updateObject']);
//        Route::post('save-object/{id}', [IllegalObjectController::class, 'saveObject']);
        Route::post('update-checklist', [IllegalObjectController::class, 'updateCheckList']);
        Route::get('object-history/{id}', [IllegalObjectController::class, 'objectHistory']);
        Route::get('check-list-history/{id}', [IllegalObjectController::class, 'checklistHistory']);
    });


    Route::group(['prefix' => 'control'], function () {
        Route::post('create-internal', [QualityControlController::class, 'createInternal']);
        Route::post('update-internal', [QualityControlController::class, 'updateInternal']);
        Route::get('material', [QualityControlController::class, 'materialList']);
        Route::get('report', [QualityControlController::class, 'report']);
        Route::get('/{id?}', [QualityControlController::class, 'index']);
    });

    Route::group(['prefix' => 'concrete'], function () {
        Route::post('create', [ConcreteWorkController::class, 'create']);
        Route::post('update', [ConcreteWorkController::class, 'update']);
        Route::get('/report', [ConcreteWorkController::class, 'report']);
        Route::get('/{id?}', [ConcreteWorkController::class, 'index']);
    });

    Route::group(['prefix' => 'claim'], function () {
        Route::get('tasks', [ClaimController::class, 'tasksList']);
        Route::post('resend', [ClaimController::class, 'resend']);
        Route::get('statistics', [ClaimController::class, 'statisticsQuantity']);
        Route::get('organization-statistics', [ClaimController::class, 'organizationStatisticsQuantity']);
        Route::get('task/{id}', [ClaimController::class, 'showTask']);
        Route::get('objects/{id}', [ClaimController::class, 'getObjects']);
        Route::get('get-pdf', [ClaimController::class, 'getPDF']);
        Route::post('conclusion-compliance', [ClaimController::class, 'conclusionCompliance']);
        Route::get('get-conclusion-pdf', [ClaimController::class, 'getConclusionPDF']);
        Route::get('get-myhome-data', [ClaimController::class, 'getMyHomeData']);
        Route::post('attach-homeid', [ClaimController::class, 'attachHomeid']);
        Route::get('task-histories/{id}', [ClaimController::class, "tasksHistories"]);
        Route::post('send-to-minstroy', [ClaimController::class, "sendToMinstroy"]);
        Route::post('accept-task', [ClaimController::class, "acceptTask"]);
        Route::post('update', [ClaimController::class, "update"]);
        Route::post('attach-object', [ClaimController::class, "attachObject"]);
        Route::post('attach-blocks', [ClaimController::class, "attachBlockAndOrganization"]);
        Route::post('conclusion-organization', [ClaimController::class, "conclusionOrganization"]);
        Route::post('conclusion-by-inspector', [ClaimController::class, "conclusionClaimByInspector"]);
        Route::post('reject-by-operator', [ClaimController::class, "rejectByOperator"]);
        Route::post('conclusion-by-director', [ClaimController::class, "conclusionClaimByDirector"]);
        Route::post('reject-from-director', [ClaimController::class, "rejectFromDirector"]);
        Route::post('send-to-director', [ClaimController::class, "sendToDirector"]);
        Route::post('manual-accept', [ClaimController::class, "manualAccept"]);
    });

});

Route::middleware('auth.custom_basic')->prefix('mygov')->group(function () {
    Route::get('get-object-by-task-id/{id}', [MyGovController::class, 'showTask']);
    Route::get('get-objects-by-pinfl', [MyGovController::class, 'getObjectsByPinfl']);
    Route::get('get-objects-by-customer', [MyGovController::class, 'getObjectsByCustomer']);
    Route::get('object-get-declaration', [MyGovController::class, 'getObjectDeclaration']);
    Route::get('get-register-by-cadastre', [MyGovController::class, 'getRegisterByCadastre']);
    Route::get('get-claim-by-cadastre', [MyGovController::class, 'getClaimByCadastre']);
});

Route::middleware('auth.custom_basic')->prefix('internal')->group(function () {
    Route::get('object-by-task-id/{id}', [ApiController::class, 'showTask']);
    Route::post('update-presentation', [ApiController::class, 'updatePresentation']);
    Route::get('object-get-shaffof/{taskId}', [ApiController::class, 'getByTaskId']);
    Route::post('update-deadline', [ApiController::class, 'updateDeadline']);
    Route::get('get-objects-by-cadasr', [MyGovController::class, 'getObjectsByCadastr']);
    Route::post('get-objects-list', [MyGovController::class, 'getObjectsList']);
    Route::get('get-objects-by-organization', [MyGovController::class, 'getObjectsByOrganization']);
    Route::get('get-objects-by-design', [MyGovController::class, 'getObjectsByDesign']);
    Route::get('get-objects-regulations', [MyGovController::class, 'getObjectsRegulations']);
    Route::get('get-object-tax', [MyGovController::class, 'getObjectTax']);
    Route::get('get-object-reestr', [MyGovController::class, 'getObjectReestr']);
    Route::get('get-claim-objects', [ApiController::class, 'getClaimObjects']);
    Route::get('get-object-images/{id}', [ApiController::class, 'getObjectImages']);
    Route::get('get-object-by-guid/{guid}', [ApiController::class, 'getObjectByGuid']);
    Route::get('get-objects-list', [ApiController::class, 'getObjectList']);
    Route::get('object-params', [ApiController::class, 'getObjectParams']);
    Route::get('object-get-blocks', [ApiController::class, 'objectGetBlocks']);
    Route::get('object-get-location', [ApiController::class, 'objectGetLocation']);
    Route::get('object-get-count', [ApiController::class, 'objectGetCount']);
    Route::get('object-get-pdf', [ApiController::class, 'objectGetPdf']);
    Route::get('get-org-objects-list/{inn}', [ApiController::class, 'getOrgObjectsList']);
    Route::get('get-objects-info/{task_id}', [ApiController::class, 'getObjectInfoByTaskId']);
    Route::get('conclusion-pdf', [ApiController::class, 'getConclusionPDF']);
    Route::get('get-lab-contract', [ApiController::class, 'getLabCont']);
    Route::get('get-object-sert', [ApiController::class, 'getObjectSert']);
    Route::get('get-data-operator', [ApiController::class, 'getDataOperator']);
    Route::get('get-inspectors', [ApiController::class, 'getInspectors']);
    Route::get('object-get-procuracy', [ApiController::class, 'objectGetProcuracy']);
    Route::get('object-detail', [ApiController::class, 'objectDetail']);
    Route::get('object-conclusion/{id}', [ApiController::class, 'objectConclusion']);
    Route::get('object-mygov-files/{id}', [ApiController::class, 'mygovFiles']);
    Route::get('object-info/{id}', [ApiController::class, 'objectInfo']);
    Route::get('object-by-inn', [InformationController::class, 'userObjects']);
    Route::get('block-info', [ApiController::class, 'blockInfo']);
    Route::get('object-tender', [ApiController::class, 'objectTender']);
    Route::get('extended-info', [ApiController::class, 'extendedInfo']);
    Route::get('object-statistic', [ApiController::class, 'objectStatistic']);
    Route::get('regulations', [ApiController::class, 'regulations']);
    Route::get('user-objects', [ApiController::class, 'userObjects']);
    Route::get('register-count', [ApiController::class, 'registerCount']);
    Route::post('ai-control', [ApiController::class, 'createControl']);
    Route::get('get-object-urban', [ApiController::class, 'getObjectUrban']);
    Route::get('object-by-role/{pin}', [ApiController::class, 'getObjectByRole']);
    Route::get('object-by-sphere', [ApiController::class, 'getObjectBySphere']);
    Route::get('get-object', [ApiController::class, 'getObjectById']);
    Route::get('get-user-regulations', [ApiController::class, 'getUserRegulations']);
    Route::get('get-claim-fvv', [ApiController::class, 'getUserRegulations']);
});

Route::post('ogohfuqaro/appeal', [OgohAppealController::class, 'receive'])->middleware('ogohfuqaro');

Route::get('get-claimgov-confirmed-objects',[ApiController::class,'getClaimGovObjects'])->middleware('manually_basic_auth');




