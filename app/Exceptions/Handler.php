<?php

namespace App\Exceptions;

use Illuminate\Http\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use GuzzleHttp\Exception\ClientException;
use App\Traits\Response as ResponseTrait;
use Throwable;

class Handler extends ExceptionHandler
{
    use ResponseTrait;
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        
        if ($exception instanceof NotFoundHttpException) {
            return $this->error('Resource url not found.', Response::HTTP_NOT_FOUND);
        }

        if($exception instanceof HttpException){
            $code  = $exception->getStatusCode();
            $message = Response::$statusTexts[$code];

            return $this->error($message, $code);
        }

        if($exception instanceof ModelNotFoundException){
            $model  = strtolower(class_basename($exception->getModel()));

            return $this->error("Does not exist any instance of {$model} with the given id", Response::HTTP_NOT_FOUND);
        }

        if($exception instanceof AuthorizationException){
            return $this->error($exception->getMessage(), Response::HTTP_FORBIDDEN);
        }

        if($exception instanceof AuthenticationException){
            return $this->error($exception->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        if($exception instanceof ValidationException){
            $errors = $exception->validator->errors()->getMessages();
            return $this->validationErrors($errors);
        }

        if($exception instanceof ClientException){
            $message = $exception->getResponse()->getBody();
            $code = $exception->getCode();
            return $this->externalResponse($message, $code);
        }

        if(env('APP_DEBUG', false)){
            return parent::render($request, $exception);
        }

        return $this->error("Unexpected error. Try later", Response::HTTP_INTERNAL_SERVER_ERROR);

    }
}
