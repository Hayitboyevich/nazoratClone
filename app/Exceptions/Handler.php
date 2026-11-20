<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Sentry\Laravel\Integration;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {

        $this->reportable(function (Throwable $e) {
        });
//        $this->reportable(function (Throwable $e) {
//            //
//        });
    }

//    public function render($request, Throwable $exception)
//    {
//        if ($request->is('api/*')) {
//            if($exception instanceof QueryException){
//                return response()->json([
//                    'message' => 'Serverda ma\'lumotlar bazasi bilan bog\'liq xatolik yuzberdi.'
//                ], 500);
//            }
//
//            return response()->json([
//                'message' => 'Serverda kutilmagan xatolik yuz berdi.'
//            ], 500);
//        }
//
//        return parent::render($request, $exception);
//    }
}
