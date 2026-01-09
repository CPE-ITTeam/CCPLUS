<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDOException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception): \Symfony\Component\HttpFoundation\Response
    {
        // Database/query exceptions return formatted JSON string
        if ($exception instanceof PDOException || ($exception instanceof \QueryException)) {
            $msg = "Database Error";
            foreach ($exception->errorInfo as $data) {
                $msg .= " : " . $data;
            }
            return new JsonResponse([
                'result' => false,
                'msg' => $msg,
                'ccp_key' => session('ccp_key'),
            ]);
        }
        return parent::render($request, $e);
    }
}
