<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use App\Models\Policy;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExcelImport implements ToModel, WithHeadingRow
{
    protected $importDate;

    public function __construct($importDate)
    {
        $this->importDate = $importDate;
    }

    public function model(array $row)
    {
        $existingRecord = Policy::firstOrNew([
            'policy_no' => $row['policy_no'],
        ]);

        Log::info($row);

        if (!empty($this->importDate)) {
            $existingRecord->policy_start_date = Carbon::parse($this->importDate);
        } else {
            $existingRecord->policy_start_date = $this->parseDate($row['policy_start_date']);
        }
        $existingRecord->policy_end_date = $existingRecord->policy_start_date->copy()->addYear();
        $net_amount = isset($row['premium']) ? $row['premium'] - $row['premium'] * 0.1525 : null;
        $discount = $row['discount'] ?? null;


        // Consider 59% discount if discount is null or 0 for payout calculation
        $effectiveDiscount = (!isset($discount) || $discount == 0) ? 59 : $discount;
        $payout = (isset($net_amount) && isset($effectiveDiscount)) ? ($net_amount * $effectiveDiscount / 100) : null;
        $payout = isset($payout) ? round($payout, 2) : null;
        $existingRecord->fill([
            'payment_by' => isset($row['payment_by']) ? strtoupper(trim($row['payment_by'])) : null,
            'company_id' => isset($row['insurance_company']) ? getCompanyId($row['insurance_company']) : null,
            'customername' => $row['customername'] ?? null,
            'discount' => $discount,
            'agent_id' => isset($row['commission_code']) ? getAgentId($row['commission_code']) : null,
            'premium' => $row['premium'] ?? null,
            'gst' => isset($row['premium']) ? $row['premium'] * 0.1525 : null,
            'agent_commission' => isset($row['commission_code']) ? getCommission($row['commission_code'], $row['premium']) : null,
            'net_amount' => $net_amount,
            'payout' => $payout,
        ]);

        $existingRecord->save();

        return $existingRecord;
    }

    protected function parseDate($value)
    {
        if (is_numeric($value)) {
            $excelBaseDate = strtotime('1899-12-30');
            $dateInSeconds = ($value) * 24 * 60 * 60;
            $unixTimestamp = $excelBaseDate + $dateInSeconds;
            return Carbon::createFromTimestamp($unixTimestamp)->startOfDay();
        } else {
            return Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
        }
    }

    public function headingRow(): int
    {
        return 1;
    }
}
