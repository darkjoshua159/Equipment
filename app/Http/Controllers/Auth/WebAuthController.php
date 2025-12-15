<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class WebAuthController extends Controller
{
    /**
     * Handle user registration.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        // 1. Validate the incoming request data
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Create the user
        $user = User::create([
            'first_name' => $request->firstname, // Map form field to database column
            'last_name' => $request->lastname,   // Map form field to database column
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // 3. Return a successful response (HTTP 201 Created)
        return response()->json([
            'message' => 'User registered successfully! Email verification required.',
            'user_id' => $user->id 
        ], 201);
    }

    /**
     * Handle user login.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // 1. Validate the login credentials
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed.'], 422);
        }

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // 2. Generate an API token using Sanctum (assuming Sanctum is set up)
            $token = $user->createToken('authToken')->plainTextToken;

            // 3. Return successful response with token
            return response()->json([
                'message' => 'Login successful.',
                'token' => $token,
                'user' => $user,
            ]);
        }

        // 4. Return authentication failure
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }
}