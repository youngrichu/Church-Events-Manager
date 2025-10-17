<?php
namespace ChurchEventsManager\Events;

class RecurrenceExpander {
    /**
     * Expand event occurrences within a given [rangeStart, rangeEnd].
     * Returns an array of cloned event rows where event_date/event_end_date reflect each occurrence.
     */
    public static function expandInRange($event, $rangeStart, $rangeEnd) {
        $occurrences = [];

        // Normalize inputs
        $rangeStartTs = strtotime($rangeStart);
        $rangeEndTs = strtotime($rangeEnd);
        if ($rangeStartTs === false || $rangeEndTs === false || $rangeEndTs < $rangeStartTs) {
            // Fallback: return the original event as-is
            $occ = clone $event;
            $occurrences[] = $occ;
            return $occurrences;
        }

        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(wp_timezone_string());
        try {
            $start = new \DateTimeImmutable($event->event_date, $tz);
        } catch (\Exception $e) {
            // Invalid event start
            $occ = clone $event;
            $occurrences[] = $occ;
            return $occurrences;
        }

        $duration = null;
        if (!empty($event->event_end_date)) {
            try {
                $end = new \DateTimeImmutable($event->event_end_date, $tz);
                $duration = $end->getTimestamp() - $start->getTimestamp();
                if ($duration < 0) { $duration = null; }
            } catch (\Exception $e) {
                $duration = null;
            }
        }

        $pattern = isset($event->recurring_pattern) ? $event->recurring_pattern : '';
        $isRecurring = isset($event->is_recurring) ? (int)$event->is_recurring : 0;
        $interval = isset($event->recurring_interval) ? max(1, (int)$event->recurring_interval) : 1;
        $until = null;
        if (!empty($event->recurring_end_date)) {
            try {
                $until = new \DateTimeImmutable($event->recurring_end_date, $tz);
            } catch (\Exception $e) {
                $until = null;
            }
        }
        $count = isset($event->recurring_count) ? (int)$event->recurring_count : null;

        $rangeStartDt = (new \DateTimeImmutable('@' . $rangeStartTs))->setTimezone($tz);
        $rangeEndDt = (new \DateTimeImmutable('@' . $rangeEndTs))->setTimezone($tz);

        // Non-recurring: include only if within range
        if (!$isRecurring || !$pattern) {
            if ($start >= $rangeStartDt && $start <= $rangeEndDt) {
                $occ = clone $event;
                $occ->event_date = $start->format('Y-m-d H:i:s');
                $occ->event_end_date = $duration !== null ? (new \DateTimeImmutable('@' . ($start->getTimestamp() + $duration)))->setTimezone($tz)->format('Y-m-d H:i:s') : null;
                $occurrences[] = $occ;
            }
            return $occurrences;
        }

        // Recurring: advance to first occurrence not before rangeStart
        $current = $start;
        $produced = 0; // number of occurrences counted since start (including those before range)

        $advanceToRangeStart = function() use ($pattern, $interval, $start, $rangeStartDt) {
            $curr = $start;
            if ($curr >= $rangeStartDt) {
                return $curr;
            }
            switch ($pattern) {
                case 'daily':
                    $daysDiff = (int) floor(($rangeStartDt->getTimestamp() - $curr->getTimestamp()) / 86400);
                    $steps = (int) ceil($daysDiff / $interval);
                    if ($steps > 0) {
                        $curr = $curr->modify('+' . ($steps * $interval) . ' days');
                    }
                    break;
                case 'weekly':
                    $daysDiff = (int) floor(($rangeStartDt->getTimestamp() - $curr->getTimestamp()) / 86400);
                    $weeksDiff = (int) floor($daysDiff / 7);
                    $steps = (int) ceil($weeksDiff / $interval);
                    if ($steps > 0) {
                        $curr = $curr->modify('+' . ($steps * $interval) . ' weeks');
                    }
                    // If still before start, bump by interval weeks until >= rangeStart
                    while ($curr < $rangeStartDt) {
                        $curr = $curr->modify('+' . $interval . ' weeks');
                    }
                    break;
                case 'monthly':
                    $y1 = (int) $curr->format('Y');
                    $m1 = (int) $curr->format('n');
                    $y2 = (int) $rangeStartDt->format('Y');
                    $m2 = (int) $rangeStartDt->format('n');
                    $monthsDiff = ($y2 - $y1) * 12 + ($m2 - $m1);
                    $steps = (int) ceil($monthsDiff / $interval);
                    if ($steps > 0) {
                        $curr = $curr->modify('+' . ($steps * $interval) . ' months');
                    }
                    // If still before start, bump by interval months until >= rangeStart
                    while ($curr < $rangeStartDt) {
                        $curr = $curr->modify('+' . $interval . ' months');
                    }
                    break;
                default:
                    // Unknown pattern: treat as non-recurring
                    return $curr;
            }
            return $curr;
        };

        $current = $advanceToRangeStart();

        // Track occurrences count from the pattern start
        $occurrencesSinceStart = function(\DateTimeImmutable $at) use ($pattern, $interval, $start) {
            switch ($pattern) {
                case 'daily':
                    $daysDiff = (int) floor(($at->getTimestamp() - $start->getTimestamp()) / 86400);
                    return (int) floor($daysDiff / $interval);
                case 'weekly':
                    $weeksDiff = (int) floor(($at->getTimestamp() - $start->getTimestamp()) / (7 * 86400));
                    return (int) floor($weeksDiff / $interval);
                case 'monthly':
                    $y1 = (int) $start->format('Y');
                    $m1 = (int) $start->format('n');
                    $y2 = (int) $at->format('Y');
                    $m2 = (int) $at->format('n');
                    $monthsDiff = ($y2 - $y1) * 12 + ($m2 - $m1);
                    return (int) floor($monthsDiff / $interval);
                default:
                    return 0;
            }
        };

        $produced = $occurrencesSinceStart($current);

        while ($current <= $rangeEndDt) {
            // Respect UNTIL/end date
            if ($until && $current > $until) { break; }
            // Respect COUNT
            if ($count !== null && $produced >= $count) { break; }

            $occ = clone $event;
            $occ->event_date = $current->format('Y-m-d H:i:s');
            $occ->event_end_date = $duration !== null ? (new \DateTimeImmutable('@' . ($current->getTimestamp() + $duration)))->setTimezone($tz)->format('Y-m-d H:i:s') : null;
            // Mark as occurrence (optional, not used by renders but useful for future features)
            $occ->occurrence_parent_id = $event->ID;
            $occ->is_occurrence = 1;
            $occurrences[] = $occ;

            // Next occurrence
            switch ($pattern) {
                case 'daily':
                    $current = $current->modify('+' . $interval . ' days');
                    break;
                case 'weekly':
                    $current = $current->modify('+' . $interval . ' weeks');
                    break;
                case 'monthly':
                    $current = $current->modify('+' . $interval . ' months');
                    break;
                default:
                    $current = $current->modify('+1 day');
                    break;
            }
            $produced++;
        }

        return $occurrences;
    }
}