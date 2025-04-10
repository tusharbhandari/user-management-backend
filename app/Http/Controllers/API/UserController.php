<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%");
        }

        $users = $query->paginate(10); // 10 per page

        return response()->json([
            'status' => true,
            'data'   => $users
        ], 200);
    }



    public function store(Request $request)
    {
        $usersData = $request->input('users');

        $errors = [];
        $validUsers = [];

        foreach ($usersData as $index => $userData) {
            $validator = Validator::make($userData, [
                'name'     => 'required|string|max:255',
                'email'    => 'required|email|unique:users,email',
                'role'     => 'required|in:Project Manager,Team Lead,Developer',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                $errors[$index] = $validator->errors();
            } else {
                $validUsers[] = [
                    'name'     => $userData['name'],
                    'email'    => $userData['email'],
                    'role'     => $userData['role'],
                    'password' => Hash::make($userData['password'])
                ];
            }
        }

        if (!empty($errors)) {
            return response()->json([
                'status'  => false,
                'message' => 'Validation failed for some records.',
                'errors'  => $errors,
            ], 422);
        }

        DB::beginTransaction();

        try {
            User::insert($validUsers);
            DB::commit();

            return response()->json([
                'status'         => true,
                'message'        => 'Users added successfully.',
                'inserted_count' => count($validUsers),
            ], 201);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('User Insertion Error: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to insert users.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);
        if ($user) {
            $user->update($request->all());
            return response()->json(['message' => 'User updated'], 200);
        }
        return response()->json(['message' => 'User not found'], 404);
    }

    public function softDelete($id)
    {
        $user = User::find($id);
        if ($user) {
            $user->delete();
            return response()->json(['message' => 'User deleted'], 200);
        }
        return response()->json(['message' => 'User not found'], 404);
    }

    public function batchDelete(Request $request)
    {
        User::whereIn('id', $request->ids)->delete();
        return response()->json(['message' => 'selected users are deleted']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
