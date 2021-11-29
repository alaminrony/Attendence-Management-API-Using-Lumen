<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function getManagers()
    {
        $role = Role::where('role', 'manager')->first();

        return response()->json([
            'Status' => 'Success',
            'Message' => 'Data Retrieved Successfully',
            'Data' => User::where('role_id', $role->id)->get()
        ], Response::HTTP_OK);
    }
}
