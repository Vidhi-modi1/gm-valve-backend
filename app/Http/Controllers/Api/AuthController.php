<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Role;
use Illuminate\Support\Str;
use Carbon\Carbon;


class AuthController extends Controller
{
    private function getDeviceInfo($userAgent)
    {
        $browser = 'Unknown';
        $os = 'Unknown';
    
        // Browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        }
    
        // OS detection
        if (strpos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $os = 'MacOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($userAgent, 'iPhone') !== false) {
            $os = 'iOS';
        }
    
        return [
            'browser' => $browser,
            'os' => $os
        ];
    }

    private function generateDeviceName($visitorId)
{
    $names = [
        'Binal Ghoniya','Raj Khaniya','Bhautik Vadi','Hardik Goswami','Akash',
        'Pradip','Chandan','Devang','Gourav','Pramod','Sarangi','Yadu',
        'Dhamkaka','Ravi Roriya','Sahil sanura 1','Sahil sanura 2','Dilip',
        'Sahil','Mayur','Siddhart bhai','Jaydipbhai','Ravi','Jatin','Bharat',
        'Nikhil','Bhumika','Niraj','Nirav','Darshil','Kishan','Juli','Jaydeep',
        'Meet','Kasim','Parag'
    ];

    $usedNames = UserDevice::pluck('device_name')->toArray();

    $availableNames = array_diff($names, $usedNames);

    if (empty($availableNames)) {
        return 'Device_' . substr($visitorId, -5);
    }

    // FIX: reindex first
    $availableNames = array_values($availableNames);

    return $availableNames[array_rand($availableNames)];
}
    
    
    public function getDeviceName(Request $request)
    {
        $request->validate([
            'visitor_id' => 'required|string'
        ]);
    
        $visitorId = $request->visitor_id;
    
        // Check if already exists
        $device = UserDevice::where('visitor_id', $visitorId)->first();

        $deviceName = $device ? $device->device_name : $this->generateDeviceName($visitorId);

        $device = UserDevice::updateOrCreate(
            ['visitor_id' => $visitorId],
            [
                'user_id'     => $user->id,
                'device_name' => $deviceName,
                'ip_address'  => $request->ip(),
                'browser_name'=> $deviceInfo['browser'],
                'os_name'     => $deviceInfo['os'],
                'is_active'   => 1,
                'last_activity_at' => now()
            ]
        );
    
        return response()->json([
            'status' => true,
            'device_name' => $deviceName
        ]);
    }

    
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|string', // username OR email
            'password' => 'required|string|min:4',
            'visitor_id' => 'required|string'
        ]);
    
        $user = User::with('role')
            ->where(function ($q) use ($request) {
                $q->where('email', $request->email)
                  ->orWhere('username', $request->email);
            })
            ->first();
    
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid username/email or password.',
            ], 401);
        }
    
        $visitorId = $request->visitor_id;
		if ($user->username !== 'ciesto' && $user->username !== 'SOA-status') {
          // Check if user already logged in from different visitor
          $activeDevice = UserDevice::where('user_id', $user->id)
              ->where('is_active', 1)
              ->first();

          if ($activeDevice && $activeDevice->visitor_id !== $visitorId) {

              return response()->json([
                  'status' => false,
                  'message' => 'Already logged in from ' . $activeDevice->device_name
              ], 403);
          }
        }

        $userAgent = $request->header('User-Agent');
        $deviceInfo = $this->getDeviceInfo($userAgent);
    
        // Create or update device
        $device = UserDevice::updateOrCreate(
            ['visitor_id' => $visitorId],
            [
                'user_id'     => $user->id,
                'device_name' => $this->generateDeviceName($visitorId),
                'ip_address'  => $request->ip(),
                'browser_name'=> $deviceInfo['browser'],
                'os_name'     => $deviceInfo['os'],
                'is_active'   => 1,
                'last_activity_at' => now()
            ]
        );
    
        
        $token = $user->createToken('api_token')->plainTextToken;
        
    
        $roleStages = \DB::table('role_stage_mapping')
            ->join('stages', 'role_stage_mapping.stage_id', '=', 'stages.id')
            ->where('role_stage_mapping.role_id', $user->role_id)
            ->select(
                'stages.id as stage_id',
                'stages.name as stage_name',
                'stages.sequence',
                'role_stage_mapping.can_edit',
                'role_stage_mapping.can_split'
            )
            ->orderBy('stages.sequence', 'asc')
            ->get();
            

            $password = 'GM6070#';
            $hash = Hash::make($password);


        return response()->json([
            'status'  => true,
            'message' => 'Login successful.',
            'data'    => [
                'user' => [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'username'  => $user->username,
                    'email' => $user->email,
                    'role'  => [
                        'id'   => $user->role->id ?? null,
                        'name' => $user->role->name ?? null,
                    ],
                ],
                'stages' => $roleStages,
                'token'  => $token,
            ],
        ]);
    }


    public function logout(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'visitor_id' => 'required|string'
        ]);
        $user = User::where('username', $request->username)->first();
    
        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found',
            ], 404);
        }
    
       
        $visitorId = $request->visitor_id;

        $device = UserDevice::where('visitor_id', $visitorId)->first();
    
        if ($device) {
            $device->update([
                'is_active' => 0
            ]);
        }
        // delete ALL tokens of this user
        $user->tokens()->delete();
    
        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully',
        ]);
    }


    public function forceLogout(Request $request)
    {
        $username = $request->query('username');
    
        $request->validate([
            'username' => 'required',
            'visitor_id' => 'required|string'
        ]);
        $user = User::where('username', $request->username)->first();
    
        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found',
            ], 404);
        }
    
       
        $visitorId = $request->visitor_id;

        $device = UserDevice::where('visitor_id', $visitorId)->first();
    
        if ($device) {
            $device->update([
                'is_active' => 0
            ]);
        }
        // delete ALL tokens of this user
        $user->tokens()->delete();
    
        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully',
        ]);
    
    }
 
}
