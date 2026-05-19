<?php

namespace App\Livewire\Billing\Reports;

use App\Services\Billing\IntegratedPositionReport;
use Livewire\Component;

class IntegratedPosition extends Component
{
    public function render(IntegratedPositionReport $report)
    {
        return view('livewire.billing.reports.integrated-position', [
            'report' => $report->build(),
        ]);
    }
}
