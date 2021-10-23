<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\HasApiTokens;
use Laravel\Lumen\Auth\Authorizable;
use Illuminate\Http\Request;
use Laravel\Passport\Client as OClient; 
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use HasApiTokens, Authenticatable, Authorizable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'token', 'password_reset_link_at', 'email_verified_at'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'token', 'password_reset_link_at', 'email_verified_at', 'created_at', 'updated_at'
    ];

    public function sendVerificationEmail(){
        $token = base64_encode($this->token);
        $email_link = route('user.emailVerificationLink', ['token' => $token]);

        //send mail
        

        return $email_link;
    }

    public function sendForgetPasswordEmail(){
        $token = base64_encode($this->token);
        $reset_password_link = url("reset-password/".$token);
        
        //send mail
        

        return $reset_password_link;
    }

    public function getTokenAndRefreshToken($email, $password) { 
        $oClient = OClient::where('password_client', 1)->first();

        $form_params = [
            'grant_type' => 'password',
            'client_id' => $oClient->id,
            'client_secret' => $oClient->secret,
            'username' => $email,
            'password' => $password,
            'scope' => '*',
        ];

        $request = Request::create(env('APP_URL').'/api/v1/oauth/token', 'POST', $form_params);           
        $response = app()->handle($request);
        
        $content = $response->getContent();
        $statusCode = $response->getStatusCode();
        //return response($content, $statusCode)->header('Content-Type', 'application/json');
        
        return [json_decode($content, true), $statusCode];
    }

    public static function getAccessTokenFromRefreshToken($refresh_token) { 
        $oClient = OClient::where('password_client', 1)->first();

        $form_params = [
            'grant_type' => 'refresh_token',
            'client_id' => $oClient->id,
            'client_secret' => $oClient->secret,
            'refresh_token' => $refresh_token,
            'scope' => '*',
        ];

        $request = Request::create(env('APP_URL').'/api/v1/oauth/token', 'POST', $form_params);           
        $response = app()->handle($request);
        
        $content = $response->getContent();
        $statusCode = $response->getStatusCode();
        //return response($content, $statusCode)->header('Content-Type', 'application/json');
        
        return [json_decode($content, true), $statusCode];
    }

    public function logOut($tokenId, $tokenRepository=null, $refreshTokenRepository=null){
        
        !$tokenRepository ? $tokenRepository = app(TokenRepository::class) : "";
        !$refreshTokenRepository ? $refreshTokenRepository = app(RefreshTokenRepository::class) : "";
        
        // Revoke an access token
        $tokenRepository->revokeAccessToken($tokenId);

        // Revoke all of the token's refresh tokens
        $refreshTokenRepository->revokeRefreshTokensByAccessTokenId($tokenId);

    }

}
