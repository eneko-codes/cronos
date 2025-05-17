<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class UserDashboardController extends Controller
{
    /**
     * Display the user dashboard.
     *
     * @param  \App\Models\User|null  $user  The user model injected by route-model binding, or null.
     */
    public function __invoke(Request $request, ?User $user = null): View
    {
        $isAdmin = Auth::user()->isAdmin();
        $targetUser = $user ?? Auth::user(); // Use injected user if present, otherwise authenticated user

        return view('components.pages.dashboard', [
            'user' => $targetUser,
            'isAdmin' => $isAdmin,
            'isViewingSpecificUser' => $user !== null, // True if a user was injected via route
        ]);
    }
}
