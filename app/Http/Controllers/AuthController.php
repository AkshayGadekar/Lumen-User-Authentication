<?php

namespace App\Http\Controllers;
;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Models\User;
use GuzzleHttp\Client;
use Laravel\Passport\Client as OClient;
use Laravel\Passport\TokenRepository;
use Laravel\Passport\RefreshTokenRepository; 

class AuthController extends Controller
{
    public function signUp(Request $request){

        $validator = Validator::make($request->all(), [
            'first_name' => 'bail|required|string|max:50',
            'last_name' => 'bail|required|string|max:50',
            'email' => 'bail|required|email',
            'password' => 'bail|required|min:5|regex:/^(?=.*[1-9\W])(?=.*[a-zA-Z]).+$/',
        ],
            [
                'password.required'=>'password is required.',
                'password.min'=>'password must have at least 5 characters.',
                'password.regex'=>'password must contain letters with at least one number or symbol.',
                //'cpassword.required'=>'password is required.',
                //'cpassword.same'=>"password doesn't match"
            ]);

        if($validator->fails()) {

            return $this->validationErrors($validator->errors());

        }

        $rand_md5 = md5(uniqid(rand()));
        
        $user = User::where("email", $request->email)->first();
        if($user){
            
            throw ValidationException::withMessages(['email' => 'User with the given email address already exists.']);
            
            //check if email verified
            /*if($user->email_verified_at){
                return $this->error("User with the given email address already exists.", 422); 
            }*/
        
        }else{

            $inputs = [
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => strtolower($request->email),
                'password' => Hash::make($request->password),
                'token' => $rand_md5,
            ];
    
            $user = User::create($inputs);

        }

        //send verification email
        /*$user->sendVerificationEmail();
        return $this->success(null, "You are registered successfully, verification link has been sent to your email address.");*/
        
        //create token
        [$content, $statusCode] = $user->getTokenAndRefreshToken($request->email, $request->password);
            
        if($statusCode != 200){
            return $this->error("User registered successfully but could not logged in, try logging in please!", $statusCode, $content);
        }
        
        return $this->success($content, "User registered and logging in successfully.");

    }

    public function logIn(Request $request){

        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|email',
            'password' => 'bail|required|min:5|regex:/^(?=.*[1-9\W])(?=.*[a-zA-Z]).+$/',
            'remember_me' => 'bail|nullable|in:0,1'
        ],
            [
                'password.required'=>'password is required.',
                'password.min'=>'password must have at least 5 characters.',
                'password.regex'=>'password must contain letters with at least one number or symbol.',
            ]);

        if($validator->fails()) {

            return $this->validationErrors($validator->errors());

        }

        $remember_me = $request->remember_me;

        $user = User::where(['email' => $request->email])->first();
        if(!$user || !Hash::check($request->password, $user->password)){
            return $this->error("wrong email address or password.", 422);       
        }

        //check if email verified
        /*if(!$user->email_verified_at){
            return $this->error("Please verify your email address.", 403);
        }*/

        //create token
        [$content, $statusCode] = $user->getTokenAndRefreshToken($request->email, $request->password);
        
        if($statusCode != 200){
            return $this->error("invalid request", $statusCode, $content);
        }
        
        return $this->success($content, "User logged in successfully.");

    }
    
    public function emailVerificationLink($token){
        
        $token = base64_decode($token);
        $user = User::where('token', $token)->first();
        
        if($user){

            if($user->email_verified_at){
                return $this->error("Email address is already verified.", 403);
            }
            
            $rand_md5 = md5(uniqid(rand()));
            $user->update(['email_verified_at'=>date('Y-m-d H:i:s'), 'token' => $rand_md5]);
            
            //redirect to home "Email verified successfully, you can login now"
            return redirect(env("APP_URL"));
        
        }else{
            //redirect to failed page
            return "link expired";
        }

    }

    public function getAccessToken(Request $request){
        
        if(!$request->has('refresh_token')){
            throw ValidationException::withMessages(['refresh_token' => 'Please send refresh token.']);
        }

        //get token
        [$content, $statusCode] = User::getAccessTokenFromRefreshToken($request->refresh_token);

        if($statusCode != 200){
            return $this->error("invalid request", $statusCode, $content);
        }
        
        return $this->success($content, "Successfully got access token from refresh token.");

    }

    public function forgetPasswordLink(Request $request){
        
        $validator = Validator::make($request->all(), [
            'email'=>'bail|required|email|exists:users',
        ],[
            'email.exists'=>'User with the given email address does not exists.'
        ]);

        if($validator->fails()) {

            return $this->validationErrors($validator->errors());
        
        }
        
        $user = User::where("email", $request->email)->first();
        if(!$user->email_verified_at){
            return $this->error("Please verify your email address.", 403);
        }
        
        //send forget password email
        $user->sendForgetPasswordEmail();
        $user->update(['password_reset_link_at' => date('Y-m-d H:i:s')]);
        
        return $this->success(null, "Password reset link has been successfully sent to your email address.");

    }

    public function resetPassword(Request $request, $token){
        
        $validator = Validator::make($request->all(), [
            'password'=>'bail|required|min:5|regex:/^(?=.*[1-9\W])(?=.*[a-zA-Z]).+$/',
            'confirm_password'=>'bail|required|same:password'
        ], [
            'password.required'=>'password is required.',
            'password.min'=>'password must have at least 5 characters.',
            'password.regex'=>'password must contain letters with at least one number or symbol.',
        ]);

        if($validator->fails()) {
            return $this->validationErrors($validator->errors());
        }

        $token = base64_decode($token);
        $user = User::where('token', $token)->first();
        if(!$user){
            return $this->error("Sorry, password reset link is expired.", 403);
        }
        if(!$user->email_verified_at){
            return $this->error("Please verify your email address.", 403);
        }

        //validate link
        $nowDatetime = new \DateTime(date('Y-m-d H:i:s'));
        $password_reset_link_at = new \DateTime(date('Y-m-d H:i:s', strtotime($user->password_reset_link_at)));
        $duration = $nowDatetime->diff($password_reset_link_at);
        
        if($duration->h < 1){

            if(Hash::check($request->password, $user->password)){
                return $this->error("Old password is not allowed.", 422);
            }
            
            $rand_md5 = md5(uniqid(rand()));
            $user->password = Hash::make($request->password);
            $user->token = $rand_md5;
            $user->save();    

            //revoke tokens
            $tokenRepository = app(TokenRepository::class);
            $refreshTokenRepository = app(RefreshTokenRepository::class);
            
            $non_revoked_tokens = $user->tokens()->where("revoked", false)->get();
            foreach ($non_revoked_tokens as $this_token) {
                $user->logOut($this_token->id, $tokenRepository, $refreshTokenRepository);
            }

            return $this->success(null, "Password has been successfully reset, please login!");
            
        }else{
            $rand_md5 = md5(uniqid(rand()));
            $user->update(['token' => $rand_md5]);
            return $this->error("Sorry, password reset link is expired.", 403);
        }

    }

    public function logOut(){
        
        $user = auth()->user();

        $tokenId = $user->token()->id;
        
        $user->logOut($tokenId);

        return $this->success(null, "User has logged out from application successfully.");
        
    }

    public function getUser(){
        return auth()->user();
    }

}
