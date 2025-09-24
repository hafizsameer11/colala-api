<?php 



namespace App\Services;

use App\Mail\OtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class UserService
{
    public function create($data){
         if (isset($data['profile_picture']) && $data['profile_picture']) {
            $path = $data['profile_picture']->store('profile_picture', 'public');
            $data['profile_picture'] = $path;
        }
        return User::create($data);
    }
    public function createUserCode($name){
        $name = explode(" ", $name);
        $code = "";
        foreach($name as $n){
            $code .= substr($n, 0, 1);
        }
        return strtoupper($code);
    }
    public function login($data){
        $user=User::where('email', $data['email'])->first();
        if(!$user){
            throw new \Exception('Email is not registered');
        }
        //now match password
        if($user && Hash::check($data['password'], $user->password)){
            return $user->load('wallet');
        }
        throw new \Exception('Password is incorrect');
        // return User::where('email', $data['email'])->first();
    }
    public function forgetPassword($email){
        $user=User::where('email', $email)->first();
        if(!$user){
            throw new \Exception('Email is not registered');
        }
        $otp=rand(1000,9999);
        $user->otp=$otp;
        $user->save();
        Mail::to($user->email)->send(new OtpMail($otp));
        return $user;
    }
    public function verifyOtp($email, $otp){
        $user=User::where('email', $email)->where('otp', $otp)->first();
        if(!$user){
            throw new \Exception('Invalid OTP');
        }
        return $user;
    }
    public function resetPassword($email, $password){
        $user=User::where('email', $email)->first();
        $user->password=Hash::make($password);
        $user->save();
        return $user;
    }
}