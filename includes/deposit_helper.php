<?php

if (!function_exists('toolshare_deposit_policy_map')) {
    function toolshare_deposit_policy_map(): array
    {
        return [
            'power tools' => ['multiplier' => 2.0, 'floor' => 75.0, 'cap' => 400.0],
            'construction' => ['multiplier' => 3.0, 'floor' => 125.0, 'cap' => 750.0],
            'gardening' => ['multiplier' => 1.5, 'floor' => 40.0, 'cap' => 250.0],
            'diy' => ['multiplier' => 1.25, 'floor' => 25.0, 'cap' => 150.0],
            'hand tools' => ['multiplier' => 1.25, 'floor' => 25.0, 'cap' => 150.0],
            '__default' => ['multiplier' => 1.5, 'floor' => 35.0, 'cap' => 300.0],
        ];
    }

    function toolshare_effective_daily_rate(float $priceHourly, float $priceDaily, float $priceWeekly): float
    {
        if ($priceDaily > 0) {
            return $priceDaily;
        }

        $derivedFromHourly = $priceHourly > 0 ? $priceHourly * 8 : 0.0;
        $derivedFromWeekly = $priceWeekly > 0 ? $priceWeekly / 5 : 0.0;

        return max($derivedFromHourly, $derivedFromWeekly, 0.0);
    }

    function toolshare_calculate_security_deposit(string $category, float $priceHourly, float $priceDaily, float $priceWeekly): float
    {
        $policies = toolshare_deposit_policy_map();
        $normalizedCategory = strtolower(trim($category));
        $policy = $policies[$normalizedCategory] ?? $policies['__default'];

        $effectiveDailyRate = toolshare_effective_daily_rate($priceHourly, $priceDaily, $priceWeekly);
        $deposit = max($policy['floor'], $effectiveDailyRate * $policy['multiplier']);
        $deposit = min($policy['cap'], $deposit);

        // Round to a clean price point so the deposit looks intentional to renters.
        return round(ceil($deposit / 5) * 5, 2);
    }
}
