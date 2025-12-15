<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    // --- AUTHENTICATION (Login & Register) ---

    /**
     * POST: Register a new user.
     * URL: /api/register
     */

    // Default image path for new users
    private const DEFAULT_PROFILE_IMAGE_PATH = 'storage/user_profiles/default.png';
    
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'username' => 'required|string|unique:users|max:255',
            'email' => 'required|string|email|unique:users|max:255',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Generate a 6-digit NUMERIC OTP (better for user input)
        $otpCode = rand(100000, 999999); 

        $user = User::create([
            'firstname' => $request->firstname,
            'lastname' => $request->lastname,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            
            // ⭐ ADDED: Default Role Assignment (Customer is the default role)
            'role' => 'customer', 

            // ⭐ ADDED: Default Profile Image Assignment (using the 'image' column)
            'image' => self::DEFAULT_PROFILE_IMAGE_PATH,
            
            'status' => 'Pending', 
            'otp' => $otpCode, 
        ]);

        // 1. Send OTP email
        try {
            Mail::send('emails.otp_verification', ['otp' => $otpCode, 'user' => $user], function($message) use ($user) {
                $message->to($user->email, $user->firstname)
                        ->subject('Account Verification Code');
            });
        } catch (\Exception $e) {
            // Log the error if the email fails, but continue the registration process
            Log::error('OTP Email failed to send to ' . $user->email . ': ' . $e->getMessage());
        }

        // 2. Respond, instructing the frontend to redirect to the verification page
        return response()->json([
            'message' => 'User registered successfully. Please enter the OTP sent to your email to verify your account.',
            'user_id' => $user->id, 
            'redirect_to' => 'verify.html' // Instruct frontend to redirect here
        ], 201);
    }

    /**
     * POST: Handle user login.
     * URL: /api/login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string', 
            'password' => 'required',
        ]);

        // Attempt to authenticate the user using the 'username' field 
        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'username' => ['The provided credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();

        // Check account status
        if ($user->status !== 'active') {
            // Account is pending or another status, deny access and redirect to verification
            return response()->json([
                'message' => 'Account is pending verification. Redirecting to verification page.',
                'status' => 'pending_verification',
                'user_id' => $user->id,
                'redirect_to' => 'verify.html' 
            ], 403); // 403 Forbidden
        }

        // --- Original Token Generation for Activated User ---
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            // Note: The user object returned here includes the 'image' path and 'role'.
            'user' => $user,
            'token' => $token,
        ]);
    }
    
    // --- NEW VERIFICATION METHOD ---
    
    /**
     * POST: Verify user using OTP.
     * URL: /api/verify
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'otp' => 'required|string|max:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $user = User::find($request->user_id);
        
        // 1. Check if OTP matches
        if ((string)$user->otp !== $request->otp) {
            return response()->json(['message' => 'Invalid verification code.'], 400); // 400 Bad Request
        }
        
        // 2. Verification successful: Update status and clear OTP
        $user->update([
            'status' => 'active',
            'otp' => null, // Clear the OTP field for security
        ]);

        // 3. Grant login token
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Account successfully verified and logged in.',
            'user' => $user,
            'token' => $token,
        ]);
    }

    // ------------------------------------------------------------------
    // ⭐ --- NEWLY ADDED: FORGOT PASSWORD & RESET PASSWORD --- ⭐
    // ------------------------------------------------------------------

    /**
     * POST: Initiate password reset by sending an OTP to the user's email.
     * This is the first step from 'forgot.html'.
     * URL: /api/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        // SECURITY IMPROVEMENT: Always return a success message if the input is valid,
        // even if the user is not found, to prevent email enumeration.
        if (!$user) {
            return response()->json([
                'message' => 'If a matching account exists, a reset code has been sent to the email address.'
            ], 200);
        }
        
        // Generate a new 6-digit NUMERIC OTP for password reset
        $otpCode = rand(100000, 999999); 
        
        // Store the new OTP in the user record
        $user->otp = $otpCode;
        $user->save();

        // Send the OTP email for password reset
        try {
            // Note: You should create a separate 'emails.password_reset_otp' view
            Mail::send('emails.password_reset_otp', ['otp' => $otpCode, 'user' => $user], function($message) use ($user) {
                $message->to($user->email, $user->firstname)
                        ->subject('Password Reset Code');
            });
        } catch (\Exception $e) {
            Log::error('Password Reset OTP Email failed to send to ' . $user->email . ': ' . $e->getMessage());
            // Even if email fails, return a success message to avoid status disclosure
            return response()->json(['message' => 'If a matching account exists, a reset code has been sent to the email address.'], 200);
        }

        // The secure response when the user is found and email is sent
        return response()->json([
            'message' => 'A 6-digit reset code has been sent to your email address.',
            'user_id' => $user->id,
            'redirect_to' => 'reset_password.html' // Instruct frontend to redirect here to enter OTP and new password
        ], 200);
    }

    /**
     * POST: Reset the user's password using the OTP.
     * This is the second step, typically from 'reset_password.html'.
     * URL: /api/reset-password
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|exists:users,email',
            'otp' => 'required|string|max:6',
            'password' => 'required|string|min:6|confirmed', // 'confirmed' checks for 'password_confirmation' field
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        // 1. Check if OTP matches and is not null
        if ((string)$user->otp !== $request->otp || $user->otp === null) {
            return response()->json(['message' => 'Invalid or expired reset code.'], 400); 
        }

        // 2. Verification successful: Update password and clear OTP
        $user->update([
            'password' => Hash::make($request->password),
            'otp' => null, // Clear the OTP field immediately
        ]);

        return response()->json([
            'message' => 'Password has been successfully reset. You can now log in.'
        ], 200);
    }
    
     public function forgotVerifyotp(Request $request)
    {
        try {
            // 1. Validate the incoming request data
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|max:255',
                'otp' => 'required|string|min:6|max:6', // Assuming OTP is 6 digits long
            ]);

            if ($validator->fails()) {
                // Return 422 Unprocessable Entity for validation errors
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 2. Find the user by email
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                // For security, provide a generic error message
                return response()->json([
                    'message' => 'Invalid email or OTP provided.'
                ], 404);
            }

            // 3. Check if the provided OTP matches the stored OTP
            if ($user->otp === $request->otp) {
                // OTP is valid. You can now clear the OTP column if desired,
                // but for multi-step flows, it's often best to leave it
                // until the password is successfully reset to maintain a 'token'.
                // $user->otp = null;
                // $user->save();

                // 4. Return success response
                return response()->json([
                    'message' => 'OTP verified successfully. You may now reset your password.'
                ], 200);

            } else {
                // OTP mismatch
                return response()->json([
                    'message' => 'Invalid OTP provided. Please try again or resend the code.'
                ], 401);
            }

        } catch (\Exception $e) {
            // Log the error for debugging purposes
            \Log::error('Forgot OTP Verification Failed: ' . $e->getMessage());

            // Return a generic server error response
            return response()->json([
                'message' => 'An unexpected error occurred during OTP verification.'
            ], 500);
        }
    }

    // ------------------------------------------------------------------
    // ⭐ --- END NEW: FORGOT PASSWORD & RESET PASSWORD --- ⭐
    // ------------------------------------------------------------------

    /**
     * POST: Handle user logout. (Requires authentication)
     * URL: /api/logout
     */
    public function logout(Request $request)
    {
        // Delete the current token being used
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    // --- PROFILE MANAGEMENT (CRUD - Requires Authentication) ---

    /**
     * GET: Retrieve the authenticated user's profile.
     * URL: /api/user/profile
     */
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * PUT/PATCH: Update the authenticated user's profile.
     * URL: /api/user/profile
     */
 public function update(Request $request)
    {
        $user = $request->user();

        // NOTE: The frontend JS uses 'image' as the file input name, 
        // so we changed 'image_file' back to 'image' here for validation.
        $validator = Validator::make($request->all(), [
            'firstname' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'username' => 'sometimes|required|string|unique:users,username,' . $user->id,
            'email' => 'sometimes|required|string|email|unique:users,email,' . $user->id,
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg|max:2048', // CHANGED TO 'image'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        // 1. Prepare data, excluding the file field used in the request
        $data = $request->except('image'); // Exclude the file input name

        // 2. Handle Image Upload - CRITICAL FIXES HERE
        if ($request->hasFile('image')) { // CHECKING FOR 'image' input
            
            // Delete old image if it exists AND is not the default path
            // The path stored in the database should be the relative path (e.g., 'user_profiles/old.png').
            if ($user->image && $user->image !== self::DEFAULT_PROFILE_IMAGE_PATH) {
                // IMPORTANT: Use the stored path directly, it's relative to the 'public' disk root (storage/app/public)
                Storage::disk('public')->delete($user->image); 
            }
            
            // Store new image in storage/app/public/user_profiles/
            // $path will be the relative path (e.g., 'user_profiles/new_filename.png')
            $path = $request->file('image')->store('user_profiles', 'public');

            // CRITICAL FIX: Save ONLY the relative path to the database
            $data['image'] = $path; // NOT Storage::url($path)
        }

        // 3. Update the User record
        $user->update($data);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ], 200);
    }

    /**
     * DELETE: Delete the authenticated user's account.
     * URL: /api/user/profile
     */
    public function destroy(Request $request)
    {
        $user = $request->user();
        
        // Delete the user's profile image if it exists AND is not the default
        if ($user->image && !str_contains($user->image, self::DEFAULT_PROFILE_IMAGE_PATH)) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->image));
        }
        
        // Delete all associated tokens and the user record
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully'], 204);
    }
}