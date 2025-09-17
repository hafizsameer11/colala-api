<?php 



namespace App\Services;

use App\Models\User;

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
}