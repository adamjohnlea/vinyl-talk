<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ExploreController extends Controller
{

    public function index() {
        return view('explore-index', [
            'users' => User::all(),
            'top5'  => User::withCount('posts')->orderBy('posts_count', 'desc')->take(5)->get()
        ]);
    }

}
