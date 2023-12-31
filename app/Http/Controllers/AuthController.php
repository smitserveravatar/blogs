<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\ResetPassword;
use App\Mail\ResetPasswordMail;
use Mail;

use Exception;

class AuthController extends Controller
{
    //To Store User data
    public function store(Request $request){
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed'
        ]);

        try{
            $user = User::where('email',$request->email)->first();
            
            if($user){
                return response()->json([
                    'message'=>'Email Already Registerd!',
                ],500);
            }else{
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => bcrypt($request->password),
                ]);    
            }
            
            $token = $user->createToken('userToken')->accessToken;
            
            return response()->json([
                'message'=>'Sign-up Successfully.',
                'user' => $user,
                'token' => $token,
            ],200);
        } catch(\Exception $e){
            report($e);
            return response()->json([
                'message'=>'Something went Wrong!',
            ],500);
        }   
    }

    //To Login Auth User
    public function login(Request $request){

        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        
        try{
            if(auth::attempt(['email' => $request->email, 'password' => $request->password])){
                $token = auth()->user()->createToken('UserToken')->accessToken;
                return response()->json([
                    'message'=>'Login Successfull.',
                    'token'=>$token,
                ],200);
            }else{
                return response()->json([
                    'message'=>'Enter valid Details!',
                ],401);
            }
        }catch(\Exception $e){
            report($e);
            return response()->json([
                'message'=>'Data Not Found.'
            ],404);
        }
    }

    //Get Authenticate User Profile
    public function profileshow() {
        try {
            $user = auth()->user();
            return response()->json([
                'user'=>$user,
            ],200);
        } catch(Exeception $e) {
            report($e);
            return response()->json([
                'message'=> 'Something went wrong!',
            ],500);
        }
    }

    //To Change User Existing Password
    public function changePassword(Request $request){
        $request->validate([
            'oldpassword'=>'required',
            'password'=>'required|min:8|confirmed',
        ]);

        try {
            $user = auth()->user();
            if(!Hash::check($request->oldpassword, $user->password)){
                return response()->json([
                    "message" =>"Old Password Was Incorrect!",
                ],401);
            }
            else{
                $updatedUser = $user->update(['password' => bcrypt($request->password)]);
                return response()->json([
                    "message"=>"Password Changed Successfully.",
                ],200);
            } 
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                "message"=>"Something Went Wrong!",
            ],500);            
        }
    }
    
    //To Forget Password through Email 
    public function forgetPassword(Request $request){
        $request->validate([
            'email'=>'required|email|exists:users,email',
        ]);

        try {
            $email = User::where('email',$request->email)->first();
            $token = Str::random(32);    

            if(!$email){
                return response()->json([
                    'message'=>'Email Not Found!',
                ],404);
            }
            else{
                $data = ResetPassword::create([
                    'email' => $request->email,
                    'token' => $token,
                    'created_at' => Carbon::now(),
                ]);

                //Send mail on User's Requested Email if Email Exists 
                Mail::to($request->email)->send(new ResetPasswordMail($token));
            
                return response()->json([
                    'message'=>'Mail Sended Successfully.',
                    'token'=>$token,
                ],200);  
            }
        } catch (\Exception $e) {
             report($e);
             return response()->json([
                 'message'=>'Something Went Wrong!',
             ],500);
        }
    }
    
    //To Reset Password via Email
    public function resetPassword(Request $request){
        try {
            $request->validate([
                'token' => 'required',
                'email' => 'required|email|exists:users,email',
                'password' => 'required|min:8|confirmed',
            ]);
        
            $token = ResetPassword::where(['email'=>$request->email,'token'=>$request->token])->first();
            if(!$token){
                return response()->json([
                    'message'=>'Invalid Token or Email!',
                ],404);
            }
            $email = User::where('email',$token->email)->first();
            if(!$email){
                return response()->json([
                  'message'=>'Email Not Found!',  
                ],404);
            }else{
                User::where('email',$request->email)->update(['password'=>bcrypt($request->password)]);
            }
    
            $token->where('email',$token->email)->delete();
            
            return response()->json([
                'message'=>'Password Updated Successfully.',
            ],200);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message'=>'Something Went Wrong!',
            ],500);
        }        
    }

    //To Logout 
    public function logout(){
        try{
            
            auth()->user()->token()->revoke();
            return response()->json([
                'message'=>'Logged Out Successfully.',
            ],200);
        }catch(\Exception $e){
            report($e);
            return response()->json([
                'message'=>'Something Went Wrong!',
            ],500);
        }
    }
}
