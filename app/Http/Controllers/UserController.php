<?php

namespace App\Http\Controllers;

use App\Events\OurExampleEvent;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;

class UserController extends Controller
{
    public function register(Request $request) {
        $incomingFields = $request->validate([
            'username' => ['required', 'min:3', 'max:30', Rule::unique('users', 'username')],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:8', 'confirmed']
        ]);

        $incomingFields['password'] = bcrypt($incomingFields['password']);

        $user = User::create($incomingFields);
        auth()->login($user);
        return redirect('/')->with('success', 'Welcome! Your account has been created and you are logged in!');
    }

    public function login(Request $request) {
        $incomingFields = $request->validate([
            'loginusername' => ['required'],
            'loginpassword' => ['required']
        ]);
        if (auth()->attempt(['username' => $incomingFields['loginusername'], 'password' => $incomingFields['loginpassword']])) {
            $request->session()->regenerate();
            event(new OurExampleEvent(['username' => auth()->user()->username, 'action' => 'login']));
            return redirect('/')->with('success', 'You have been logged in');
        } else {
            return redirect('/')->with('failure', 'Invalid login. Please try again.');
        }
    }

    public function logout() {
        event(new OurExampleEvent(['username' => auth()->user()->username, 'action' => 'logout']));
        auth()->logout();
        return redirect('/')->with('success', 'You have been logged out');;
    }

    public function showCorrectHomepage() {
        if (auth()->check()) {
            return view('home-feed', ['posts' => auth()->user()->feedPosts()->latest()->paginate(5)]);
        } else {
            return view('home');
        }
    }

    private function getSharedData($user) {
        $currentlyFollowing = 0;
        if (auth()->check()) {
            $currentlyFollowing = Follow::where([
                ['user_id', '=', auth()->user()->id],
                ['followedUser', '=', $user->id ]
            ])->count();
        }
        View::share('sharedData', [
            'currentlyFollowing' => $currentlyFollowing,
            'username' => $user->username,
            'avatar' => $user->avatar,
            'postCount' => $user->posts()->count(),
            'followerCount' => $user->followers()->count(),
            'followingCount' => $user->followingTheseUsers()->count()
        ]);
    }

    public function profile(User $user) {
        $this->getSharedData($user);
        return view('profile-posts', [
            'posts' => $user->posts()->latest()->get(),
        ]);
    }

    public function profileFollowers(User $user) {
        $this->getSharedData($user);
        return view('profile-followers', [
            'followers' => $user->followers()->latest()->get(),
        ]);
    }

    public function profileFollowing(User $user) {
        $this->getSharedData($user);
        return view('profile-following', [
            'following' => $user->followingTheseUsers()->latest()->get(),
        ]);
    }

    public function showAvatarForm() {
        return view('avatar-form');
    }

    public function storeAvatar(Request $request) {
        $request->validate([
           'avatar' => 'required|image|max:2000'
        ]);
        $user = auth()->user();

        $filename = $user->id . '-' . uniqid() . '.jpg';

        $imgData = Image::make($request->file('avatar'))->fit(120, 120)->encode('jpg');
        Storage::put('public/avatars/' . $filename, $imgData);

        $oldAvatar = $user->avatar;

        $user->avatar = $filename;
        $user->save();

        if ($oldAvatar != '/fallback-avatar.jpg') {
            Storage::delete(str_replace('/storage/', 'public/', $oldAvatar));
        }

        return back()->with("success", "Love the new avatar! Lookin' good.");
    }
}
