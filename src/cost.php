<?php
declare(strict_types=1);
function calculateItemCost(array $params, array $printer, array $filaments, int $printTimeSeconds, float $servicesCost = 0.0, int $quantity = 1): array {
    $hours = $printTimeSeconds / 3600.0;
    $energyCost = ($printer['power_w'] / 1000.0) * $hours * $params['energy_cost_kwh'];
    $hourlyAdditional = $params['hourly_additional'] * $hours;
    $filamentCost = 0.0;
    foreach ($filaments as $f) {
        $filamentCost += ($f['grams'] / 1000.0) * $f['price_per_kg'];
    }
    $subtotal = $energyCost + $hourlyAdditional + $filamentCost + $servicesCost;
    $totalUnit = $subtotal * (1.0 + ($params['profit_margin_percent'] / 100.0));
    $total = $totalUnit * max(1, $quantity);
    return [
        'energy_cost' => round($energyCost, 2),
        'hourly_additional_cost' => round($hourlyAdditional, 2),
        'filament_cost' => round($filamentCost, 2),
        'services_cost' => round($servicesCost, 2),
        'subtotal' => round($subtotal, 2),
        'total_unit' => round($totalUnit, 2),
        'quantity' => max(1, $quantity),
        'total' => round($total, 2)
    ];
}