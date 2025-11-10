<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate {--start=} {--end=} {--weekly : Generate invoices for the last completed week}';

    protected $description = 'Generate invoices for photographers over a given period';

    public function handle(InvoiceService $service): int
    {
        $startOption = $this->option('start');
        $endOption = $this->option('end');

        if ($this->option('weekly')) {
            $invoices = $service->generateForLastCompletedWeek();
        } elseif ($startOption && $endOption) {
            $start = Carbon::parse($startOption);
            $end = Carbon::parse($endOption);
            $invoices = $service->generateForPeriod($start, $end);
        } else {
            $this->error('Please provide --start and --end dates or use the --weekly flag.');
            return self::FAILURE;
        }

        $this->info(sprintf('Generated %d invoice(s).', $invoices->count()));

        return self::SUCCESS;
    }
}
