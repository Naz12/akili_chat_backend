<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class UserApiController extends Controller
{
    public function profile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized. No user found for this token.'
            ], 401);
        }

        return response()->json($user);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            // Invalidate the token (if using JWT, you might want to blacklist it)
            // For now, we'll just return success since JWT tokens are stateless
            // If you're using database tokens, you can delete them here
            Auth::guard('api')->logout();
        }

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function index()
    {
        return response()->json(User::all());
    }

    public function show($id)
    {
        return response()->json(User::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $user->update($request->all());
        return response()->json(['message' => 'User updated successfully']);
    }

    public function storeFcmToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $user->fcm_token = $request->token;
        $user->save();

        return response()->json(['success' => true, 'message' => 'FCM token saved.']);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}