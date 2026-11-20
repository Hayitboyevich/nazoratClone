<?php

namespace App\Providers;


use App\Repositories\ArticleRepository;
use App\Repositories\BlockRepository;
use App\Repositories\ClaimGovRepository;
use App\Repositories\ClaimRepository;
use App\Repositories\ConcreteWorkRepository;
use App\Repositories\DeclarationRepository;
use App\Repositories\IllegalObjectRepository;
use App\Repositories\Interfaces\ArticleRepositoryInterface;
use App\Repositories\Interfaces\BlockRepositoryInterface;
use App\Repositories\Interfaces\ClaimGovRepositoryInterface;
use App\Repositories\Interfaces\ClaimRepositoryInterface;
use App\Repositories\Interfaces\ConcreteWorkInterface;
use App\Repositories\Interfaces\DeclarationInterface;
use App\Repositories\Interfaces\IllegalObjectRepositoryInterface;
use App\Repositories\Interfaces\LabCheckListRepositoryInterface;
use App\Repositories\Interfaces\LabRepositoryInterface;
use App\Repositories\Interfaces\MonitoringRepositoryInterface;
use App\Repositories\Interfaces\PresentationInterface;
use App\Repositories\Interfaces\QualityControlInterface;
use App\Repositories\Interfaces\RegulationRepositoryInterface;
use App\Repositories\Interfaces\ResponseRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\LabCheckListRepository;
use App\Repositories\Interfaces\LabTestContractRepositoryInterface;
use App\Repositories\Interfaces\LabTestRepositoryInterface;
use App\Repositories\LabTestContractRepository;
use App\Repositories\LabRepository;
use App\Repositories\LabTestRepository;
use App\Repositories\MonitoringRepository;
use App\Repositories\PresentationRepository;
use App\Repositories\QualityControlRepository;
use App\Repositories\RegulationRepository;
use App\Repositories\ResponseRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }


    public function boot()
    {
        $this->app->bind(ClaimRepositoryInterface::class,ClaimRepository::class);
        $this->app->bind(IllegalObjectRepositoryInterface::class,IllegalObjectRepository::class);
        $this->app->bind(ArticleRepositoryInterface::class,ArticleRepository::class);
        $this->app->bind(BlockRepositoryInterface::class,BlockRepository::class);
        $this->app->bind(UserRepositoryInterface::class,UserRepository::class);
        $this->app->bind(RegulationRepositoryInterface::class,RegulationRepository::class);
        $this->app->bind(MonitoringRepositoryInterface::class,MonitoringRepository::class);
        $this->app->bind(ResponseRepositoryInterface::class,ResponseRepository::class);
        $this->app->bind(LabRepositoryInterface::class,LabRepository::class);
        $this->app->bind(PresentationInterface::class, PresentationRepository::class);
        $this->app->bind(DeclarationInterface::class, DeclarationRepository::class);
        $this->app->bind(LabCheckListRepositoryInterface::class, LabCheckListRepository::class);
        $this->app->bind(ClaimGovRepositoryInterface::class, ClaimGovRepository::class);
        $this->app->bind(QualityControlInterface::class, QualityControlRepository::class);
        $this->app->bind(ConcreteWorkInterface::class, ConcreteWorkRepository::class);
        $this->app->bind(LabTestRepositoryInterface::class, LabTestRepository::class);
        $this->app->bind(LabTestContractRepositoryInterface::class, LabTestContractRepository::class);
    }
}
