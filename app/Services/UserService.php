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
public function createUserCode()
{
    // Prefix for brand or project
    $prefix = 'COL';

    // Generate random 2-digit number
    $number = str_pad(rand(10, 99), 2, '0', STR_PAD_LEFT);

    // Generate 4–5 random uppercase letters/numbers (excluding confusing ones)
    $suffix = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 5));

    // Final format: COL-82K4TZ
    return sprintf('%s-%s%s', $prefix, $number, $suffix);
}

    public function login($data){
        $user=User::where('email', $data['email'])->first();
        if(!$user){
            throw new \Exception('Email is not registered'.$data['email']);
        }
        //now match password
        if($user && Hash::check($data['password'], $user->password)){
            return $user->load('wallet');
        }
        throw new \Exception('Password is incorrect');
        // return User::where('email', $data['email'])->first();
    }
    public function adminLogin($data){
        $user=User::where('email', $data['email'])->where('role', 'admin')->first();
        if(!$user){
            throw new \Exception('You are not an admin');
        }
        //now match password
        if($user && Hash::check($data['password'], $user->password)){
            return $user->load('wallet');
        }
        throw new \Exception('Password is incorrect');
        // return User::where('email', $data['email'])->first();
    }
    public function sellerLogin($data){
        $user=User::where('email', $data['email'])->where('role', 'seller')->first();
        if(!$user){
            throw new \Exception('You are not a seller');
        }
        //now match password
        if($user && Hash::check($data['password'], $user->password)){
            return $user->load('wallet');
        }
        throw new \Exception('Password is incorrect');
    }
    public function forgetPassword($email){
        $user=User::where('email', $email)->first();
        if(!$user){
            throw new \Exception('Email is not registered');
        }
        
        // Prevent sending OTP too frequently (within last 60 seconds)
        // Check if user was updated recently (assuming OTP updates the user)
        if($user->updated_at && $user->updated_at->diffInSeconds(now()) < 60 && $user->otp){
            throw new \Exception('Please wait 60 seconds before requesting a new OTP. Check your email.');
        }
        
        $otp=rand(1000,9999);
        $user->otp=$otp;
        $user->save();
        
        // Send email immediately (OtpMail no longer implements ShouldQueue to prevent duplicates)
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
public function update(array $data, int $userId)
{
    $user = User::findOrFail($userId);

    // Handle profile picture upload
    if (!empty($data['profile_picture']) && $data['profile_picture'] instanceof \Illuminate\Http\UploadedFile) {
        $path = $data['profile_picture']->store('profile_picture', 'public');
        $data['profile_picture'] = $path;
    }

    // Update only the given fields
    $user->update($data);

    // Return fresh model with updated attributes
    return $user->fresh();
}

}