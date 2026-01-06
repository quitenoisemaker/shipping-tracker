<?php

namespace Quitenoisemaker\ShippingTracker\Console\Commands;

use Illuminate\Console\Command;
use Quitenoisemaker\ShippingTracker\Facades\ShippingTracker;
use Quitenoisemaker\ShippingTracker\Exceptions\ShippingException;

class CheckProviderHealthCommand extends Command
{
    protected $signature = 'shipping:check-status';
    protected $description = 'Check connectivity for all configured shipping providers';

    public function handle()
    {
        $providers = config('shipping-tracker.providers', []);
        $this->info('Checking provider health...');

        $rows = [];
        foreach ($providers as $key => $class) {
            $status = '❌ Failed';
            $error = '';
            
            try {
                // Instantiate manually to avoid Side effects of `use()` which might not be fully disconnected
                $provider = app($class);
                if (method_exists($provider, 'checkHealth')) {
                    if ($provider->checkHealth()) {
                        $status = '✅ OK';
                    } else {
                        $error = 'Health check returned false';
                    }
                } else {
                    $status = '⚠️  No Check';
                    $error = 'Method checkHealth() not implemented';
                }
            } catch (\Exception $e) {
                 $error = $e->getMessage();
            }

            $rows[] = [$key, $class, $status, $error];
        }

        $this->table(['Provider', 'Class', 'Status', 'Error'], $rows);
        return 0;
    }
}
