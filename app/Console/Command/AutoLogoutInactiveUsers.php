<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserDevice;
use Carbon\Carbon;
use Log;

class AutoLogoutInactiveUsers extends Command
{
    protected $signature = 'users:auto-logout';
    protected $description = 'Logout users inactive for 30 minutes';

    public function handle()
    {
        Log::info('Logout users inactive for 30 minutes');
        $cutoffTime = Carbon::now()->subMinutes(30);

        $devices = UserDevice::where('is_active', 1)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<', $cutoffTime)
            ->get();

        foreach ($devices as $device) {

            // delete only this user's tokens
            if ($device->user) {
                $device->user->tokens()->delete();
            }

            // mark device inactive
            $device->update([
                'is_active' => 0
            ]);

            $this->info("User {$device->user_id} logged out due to inactivity.");
        }

        return Command::SUCCESS;
    }
}