<?php

namespace Stripe_Net_Revenue;

/**
 * Streaming stats helpers.
 *
 * Designed to avoid storing large arrays in memory.
 *
 * Notes:
 * - Median is estimated via histogram buckets by default.
 * - For small N, you can store exact values if needed.
 */
final class Stats
{
    /**
     * Compute the exact median for a list of ints.
     *
     * @param int[] $values
     * @return int|null
     */
    public static function exact_median(array $values)
    {
        if (empty($values)) {
            return null;
        }
        sort($values);
        $n = count($values);
        $mid = (int)floor(($n - 1) / 2);
        if ($n % 2) {
            return (int)$values[$mid];
        }
        return (int)round(($values[$mid] + $values[$mid + 1]) / 2);
    }

    /**
     * Compute an approximate median using a fixed-width histogram.
     *
     * Improvements:
     * - interpolates within the identified bucket for better accuracy than returning midpoint
     * - tolerates sparse bucket arrays
     *
     * @param array<int,int> $bucket_counts bucketIndex => count
     * @param int $total_count
     * @param int $min
     * @param int $max
     * @param int $bucket_count
     * @return int|null
     */
    public static function approx_median_from_histogram($bucket_counts, $total_count, $min, $max, $bucket_count)
    {
        if ($total_count <= 0) {
            return null;
        }
        if ($min === $max) {
            return $min;
        }
        $bucket_count = (int)$bucket_count;
        if ($bucket_count <= 0) {
            $bucket_count = 64;
        }

        // Ensure deterministic bucket traversal.
        ksort($bucket_counts);

        $target = (int)ceil($total_count / 2);
        $cum = 0;

        $range = max(1, $max - $min);
        $width = (float)$range / (float)$bucket_count;
        if ($width <= 0) {
            $width = 1;
        }

        foreach ($bucket_counts as $bucket => $count) {
            $count = (int)$count;
            if ($count <= 0) {
                continue;
            }

            $prev = $cum;
            $cum += $count;
            if ($cum >= $target) {
                // Median falls in this bucket. Interpolate inside the bucket.
                $pos_in_bucket = max(0, $target - $prev);
                $fraction = $count > 0 ? ($pos_in_bucket / $count) : 0.5;

                $low = (float)($min + ($bucket * $width));
                $high = (float)($min + (($bucket + 1) * $width));
                $value = $low + ($high - $low) * $fraction;

                return (int)round($value);
            }
        }

        return $max;
    }

    /**
     * Add a value into a histogram.
     *
     * IMPORTANT: For accuracy, pass stable $min/$max values for the dataset/window.
     * If min/max change over time, earlier bucket assignments are not re-bucketed.
     *
     * @param int $value
     * @param int $min
     * @param int $max
     * @param int $bucket_count
     * @param array<int,int> $bucket_counts
     * @return array<int,int>
     */
    public static function histogram_add($value, $min, $max, $bucket_count, $bucket_counts)
    {
        $bucket_count = (int)$bucket_count;
        if ($bucket_count <= 0) {
            $bucket_count = 64;
        }

        if ($min === $max) {
            $bucket = 0;
        } else {
            // Clamp value to [min, max] before indexing.
            $value = max($min, min($max, (int)$value));
            $range = max(1, $max - $min);
            $idx = (int)floor((($value - $min) / $range) * $bucket_count);
            $bucket = max(0, min($bucket_count - 1, $idx));
        }

        if (!isset($bucket_counts[$bucket])) {
            $bucket_counts[$bucket] = 0;
        }
        $bucket_counts[$bucket]++;

        return $bucket_counts;
    }
}

