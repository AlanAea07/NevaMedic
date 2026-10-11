<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index()
    {
        return view('admin.accounts.index', ['accounts' => User::orderBy('name')->paginate(20)]);
    }

    public function update(Request $request, User $account)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        if ($account->is($request->user()) && ! $data['is_active']) {
            throw ValidationException::withMessages(['account' => 'No puedes desactivar tu propia cuenta administrativa.']);
        }
        DB::transaction(function () use ($account, $data, $request) {
            $before = $account->only(['id', 'name', 'role', 'is_active']);
            $account->forceFill(['is_active' => (bool) $data['is_active'], 'remember_token' => null])->save();
            if (! $data['is_active'] && config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $account->id)->delete();
            }
            ActivityLog::record($request->user(), $data['is_active'] ? 'activated' : 'deactivated', 'user', $account->id, $before, $account->only(['id', 'name', 'role', 'is_active']));
        });

        return back()->with('status', $data['is_active'] ? 'Cuenta reactivada.' : 'Cuenta desactivada. Sus datos se conservan.');
    }
}
