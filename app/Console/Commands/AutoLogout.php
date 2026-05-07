<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UserDevice;
use Carbon\Carbon;
use Log;

class AutoLogout extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:auto-logout';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
      
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
