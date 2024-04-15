<?php

namespace App\Http\Controllers;

use App\Events\OurExampleEvent;
use App\Models\Follow;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use App\Models\Post;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function storeAvatar(Request $request) {
        $request->validate([
            'avatar' => 'required|image|max:3000'
        ]);

        $user = auth()->user();
        $filename = $user->id . '-' . uniqid() . '.jpg';
        $imageData = $request->file('avatar');

        Storage::putFileAs("public/avatars/", $imageData, $filename);

        $oldAvatar = $user->avatar;

        $user->avatar = $filename;
        $user->save();

        if($oldAvatar != '/fallback-avatar.jpg') {
            Storage::delete(str_replace('/storage/', 'public/', $oldAvatar));
        }
        return back()->with('success', 'Congrats on the new avatar.');
    }

    public function showAvatarForm() {
        return view('avatar-form');
    }

    private function getSharedData($user) {
        $currentlyFollowing = 0;
        if(auth()->check()) {
            $currentlyFollowing = Follow::where([
                ['user_id', '=', auth()->user()->id],
                ['followeduser', '=', $user->id]
            ])->count();
        }

        View::share('sharedData', [
            'username' => $user->username,
            'postCount' => $user->posts()->count(),
            'avatar' => $user->avatar,
            'currentlyFollowing' => $currentlyFollowing,
            'followerCount' => $user->followers->count(),
            'followingCount' => $user->followingTheseUsers->count()
        ]);
    }

    public function profile(User $user) {
        $this->getSharedData($user);
        return view('profile-posts', [
            'posts' => $user->posts()->latest()->get()
        ]);
    }

    public function profileRaw(User $user) {
        return response()->json([
            'theHtml' => view(
                    'profile-posts-only',
                    [
                        'posts' => $user->posts()->latest()->get()
                    ]
                )->render(),
            'docTitle' => $user->username."'s Profile"
        ]);
    }

    public function profileFollowers(User $user) {
        $this->getSharedData($user);

        return view('profile-followers', [
            'followers' => $user->followers()->latest()->get()
        ]);
    }

    public function profileFollowersRaw(User $user) {
        return response()->json([
            'theHtml' => view(
                    'profile-followers-only',
                    [
                        'followers' => $user->followers()->latest()->get()
                    ]
                )->render(),
            'docTitle' => $user->username."'s Followers"
        ]);
    }

    public function profileFollowing(User $user) {
        $this->getSharedData($user);

        return view('profile-following', [
            'following' => $user->followingTheseUsers()->latest()->get()
        ]);
    }

    public function profileFollowingRaw(User $user) {
        return response()->json([
            'theHtml' => view(
                    'profile-following-only',
                    [
                        'following' => $user->followingTheseUsers()->latest()->get()
                    ]
                )->render(),
            'docTitle' => "Who " . $user->username. " Follows"
        ]);
    }

    public function showCorrectHomePage() {
       if( auth()->check()) {
            return view('homepage-feed', ['posts' => auth()->user()->feedPosts()->latest()->get()->toQuery()->paginate(4)]);
       } else {
            $postCount = Cache::remember('postCount', 60, fn() => Post::count());
            
            return view('homepage', [
                'postCount' => $postCount
            ]);
       }
    }

    public function register(Request $request) {
        $incomingFields = $request->validate([
            'username' => [
                'required',
                'min:3',
                'max:20',
                Rule::unique('users','username')
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users','email')
            ],
            'password' => [
                'required',
                'min:8',
                'confirmed'
            ]
        ]);

        $incomingFields['password'] = bcrypt($incomingFields['password']);

        $user = User::create($incomingFields);
        auth()->login($user);

        return redirect('/')->with('success', 'Thank you for creating an account.');
    }

    public function login(Request $request) {
        $incomingFields = $request->validate([
            'loginusername' => 'required',
            'loginpassword' => 'required'
        ]);

        if(auth()->attempt([
            'username' => $incomingFields['loginusername'],
            'password' => $incomingFields['loginpassword']
        ])) {
            $request->session()->regenerate();

            event(new OurExampleEvent([
                'username' => auth()->user()->username, 
                'action' => 'login'
            ]));

            return redirect('/')->with('success','You have successfully logged in.');
        } else {
            return redirect('/')->with('failure','Invalid login.');
        }
    }

    public function logout() {
        event(new OurExampleEvent([
            'username' => auth()->user()->username, 
            'action' => 'logout'
        ]));
        
        auth()->logout();
        return redirect('/')->with('success', 'You are now logged out.');
    }

    public function loginApi(Request $request) {
        $incomingFields = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        if(auth()->attempt($incomingFields)) {
            $user = User::where('username', $incomingFields['username'])->first();
            $token = $user->createToken('ourapptoken')->plainTextToken;
            return $token;
        }

        return 'Sorry';
    }
}
