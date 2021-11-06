<?php

namespace App\Traits;
use Illuminate\Http\Response as HttpResponse;

trait Response{

    public function success($data=null, $message="Request processed successfully.", $code=HttpResponse::HTTP_OK){
        
        return response()->json([
            "success" => true,
            "message" => $message,
            "data" => $data
        ], $code);

    }

    public function error($message="Request failed to processed further.", $code=HttpResponse::HTTP_INTERNAL_SERVER_ERROR, $data=null){
        
        return response()->json([
            "success" => false,
            "message" => $message,
            "data" => $data,
        ], $code);
        
    }

    public function validationErrors($data){
        
        return response()->json([
            "success" => false,
            "message" => "Validation errors.",
            "data" => $data,
        ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
        
    }

    public function externalResponse($data, $code)
    {
        
        return response($data, $code)->header('Content-Type', 'application/json');
    
    }

}