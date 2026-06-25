<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStudioUserRolesRequest;
use App\Models\User;
use App\Services\StudioUserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudioUsersController extends Controller
{
    public function index(StudioUserManagementService $userManagement): View
    {
        return view('studio.users.index', [
            'users' => $userManagement->usersForManagement(),
            'manageableRoleKeys' => StudioUserManagementService::MANAGEABLE_ROLE_KEYS,
        ]);
    }

    public function activate(User $user, StudioUserManagementService $userManagement): RedirectResponse
    {
        $userManagement->activate($user, auth()->user());

        return redirect()
            ->route('studio.users.index')
            ->with('user_updated', $user->username);
    }

    public function deactivate(User $user, StudioUserManagementService $userManagement): RedirectResponse
    {
        $userManagement->deactivate($user, auth()->user());

        return redirect()
            ->route('studio.users.index')
            ->with('user_updated', $user->username);
    }

    public function updateRoles(
        UpdateStudioUserRolesRequest $request,
        User $user,
        StudioUserManagementService $userManagement,
    ): RedirectResponse {
        $userManagement->syncRoles($user, $request->roleKeys(), auth()->user());

        return redirect()
            ->route('studio.users.index')
            ->with('user_updated', $user->username);
    }
}
