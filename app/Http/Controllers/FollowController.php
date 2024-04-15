<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use Illuminate\Http\Request;
use App\Models\User;

class FollowController extends Controller
{
   public function createFollow(User $user) {
        if($user->id == auth()->user()->id) {
            return back()->with('failure', 'You cannot follow yourself.');
        }

        $exists = Follow::where([
            ['user_id', '=', auth()->user()->id],
            ['followeduser', '=', $user->id]
        ])->count();

        if($exists) {
            return back()->with('failure', 'You are already following this user.');
        }

        $newFollow = new Follow;
        $newFollow->user_id = auth()->user()->id;
        $newFollow->followeduser = $user->id;
        $newFollow->save();

        return back()->with('success', 'User successfully followed');
   }

   public function removeFollow(User $user) {
       Follow::where([
            ['user_id', '=', auth()->user()->id],
            ['followeduser', '=', $user->id]
       ])->delete();
       
       return back()->with('success', 'User successfully unfollowed.');
   }
}
