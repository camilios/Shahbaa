<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return Role::with('user')->paginate(20);
    }

    public function show(Role $role)
    {
        return $role->load('user');
    }

    public function store(RoleRequest $request)
    {
        return Role::create($request->validated());
    }

    public function update(RoleRequest $request, Role $role)
    {
        $role->update($request->validated());

        return $role;
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return response()->noContent();
    }
}
