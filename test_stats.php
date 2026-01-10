<?php

require_once __DIR__ . '/vendor/autoload.php';

use Stripe_Net_Revenue\Stats;

function assert_eq($label, $expected, $actual)
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: $label Expected=" . var_export($expected, true) . " Actual=" . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: $label\n";
}

// Exact median tests
assert_eq('exact median odd', 3, Stats::exact_median([1, 3, 5]));
assert_eq('exact median even', 4, Stats::exact_median([2, 6]));
assert_eq('exact median even 4', 3, Stats::exact_median([1, 2, 4, 9]));

// Histogram median tests (simple)
$values = [100, 100, 100, 100, 100, 500];
$min = min($values);
$max = max($values);
$hist = [];
foreach ($values as $v) {
    $hist = Stats::histogram_add($v, $min, $max, 10, $hist);
}
$approx = Stats::approx_median_from_histogram($hist, count($values), $min, $max, 10);
// True median is 100; allow some tolerance but it should be near min.
if ($approx > 200) {
    fwrite(STDERR, "FAIL: approx median skewed too high: $approx\n");
    exit(1);
}
echo "PASS: approx median skewed distribution (approx=$approx)\n";

// Negative values scenario
$values = [-50, -10, 0, 10, 20];
$min = min($values);
$max = max($values);
$hist = [];
foreach ($values as $v) {
    $hist = Stats::histogram_add($v, $min, $max, 10, $hist);
}
$approx = Stats::approx_median_from_histogram($hist, count($values), $min, $max, 10);
// True median is 0.
if (abs($approx - 0) > 10) {
    fwrite(STDERR, "FAIL: approx median negative distribution too far: $approx\n");
    exit(1);
}
echo "PASS: approx median handles negatives (approx=$approx)\n";

echo "=== ALL STATS TESTS PASSED ===\n";

