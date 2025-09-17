<?php 



namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

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
            return $user;
        }
        throw new \Exception('Password is incorrect');
        // return User::where('email', $data['email'])->first();
    }
}