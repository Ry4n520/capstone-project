<?php
/**
 * Shared facility-booking helpers used by page rendering and APIs.
 */

function facility_booking_slots()
{
    return [
        ['start' => '08:00:00', 'end' => '10:00:00', 'label' => '8:00 AM - 10:00 AM'],
        ['start' => '10:00:00', 'end' => '12:00:00', 'label' => '10:00 AM - 12:00 PM'],
        ['start' => '12:00:00', 'end' => '14:00:00', 'label' => '12:00 PM - 2:00 PM'],
        ['start' => '14:00:00', 'end' => '16:00:00', 'label' => '2:00 PM - 4:00 PM'],
        ['start' => '16:00:00', 'end' => '18:00:00', 'label' => '4:00 PM - 6:00 PM'],
        ['start' => '18:00:00', 'end' => '20:00:00', 'label' => '6:00 PM - 8:00 PM']
    ];
}

function facility_booking_type_aliases()
{
    return [
        'classroom' => 'classroom',
        'meeting_room' => 'meeting_room',
        'meeting' => 'meeting_room',
        'sport_facility' => 'sport_facility',
        'sport' => 'sport_facility'
    ];
}

function facility_booking_normalize_type($type)
{
    $type = strtolower(trim((string) $type));
    $aliases = facility_booking_type_aliases();

    return $aliases[$type] ?? null;
}

function facility_booking_type_title($type)
{
    $normalized = facility_booking_normalize_type($type);

    if ($normalized === 'classroom') {
        return 'Classrooms';
    }

    if ($normalized === 'meeting_room') {
        return 'Meeting Rooms';
    }

    if ($normalized === 'sport_facility') {
        return 'Sport Facilities';
    }

    return 'Facilities';
}

function facility_booking_has_conflict($existingBookings, $slotStart, $slotEnd)
{
    foreach ($existingBookings as $booking) {
        $existingStart = $booking['start_time'];
        $existingEnd = $booking['end_time'];

        // Overlap if existing starts before slot end and ends after slot start.
        if ($existingStart < $slotEnd && $existingEnd > $slotStart) {
            return true;
        }
    }

    return false;
}

function facility_booking_get_existing_bookings(PDO $pdo, $facilityId, $date)
{
    $stmt = $pdo->prepare(
        'SELECT start_time, end_time
         FROM bookings
         WHERE facility_id = :facility_id
           AND booking_date = :booking_date
           AND booking_status IN (\'confirmed\', \'pending\')'
    );

    $stmt->execute([
        ':facility_id' => (int) $facilityId,
        ':booking_date' => $date
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function facility_booking_get_slots_for_date(PDO $pdo, $facilityId, $date)
{
    $existingBookings = facility_booking_get_existing_bookings($pdo, $facilityId, $date);
    $slots = facility_booking_slots();
    $result = [];
    $availableCount = 0;

    foreach ($slots as $slot) {
        $isAvailable = !facility_booking_has_conflict($existingBookings, $slot['start'], $slot['end']);

        if ($isAvailable) {
            $availableCount++;
        }

        $result[] = [
            'start' => $slot['start'],
            'end' => $slot['end'],
            'label' => $slot['label'],
            'available' => $isAvailable
        ];
    }

    return [
        'slots' => $result,
        'available_slots_count' => $availableCount
    ];
}

function facility_booking_get_facilities_with_availability(PDO $pdo, $type, $date)
{
    $normalizedType = facility_booking_normalize_type($type);
    if ($normalizedType === null) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT facility_id, facility_name, location, capacity, facility_type
         FROM facilities
         WHERE facility_type = :facility_type
         ORDER BY facility_name ASC'
    );

    $stmt->execute([':facility_type' => $normalizedType]);
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $enriched = [];
    foreach ($facilities as $facility) {
        $availability = facility_booking_get_slots_for_date($pdo, (int) $facility['facility_id'], $date);
        $facility['slots'] = $availability['slots'];
        $facility['available_slots'] = array_values(array_filter($availability['slots'], function ($slot) {
            return $slot['available'] === true;
        }));
        $facility['available_slots_count'] = $availability['available_slots_count'];
        $facility['available_count'] = $availability['available_slots_count'];
        $enriched[] = $facility;
    }

    // Most available facilities first, so fully booked facilities are at the bottom.
    usort($enriched, function ($a, $b) {
        return $b['available_slots_count'] <=> $a['available_slots_count'];
    });

    return $enriched;
}

function facility_booking_available_facility_count(PDO $pdo, $type, $date)
{
    $facilities = facility_booking_get_facilities_with_availability($pdo, $type, $date);

    $available = array_filter($facilities, function ($facility) {
        return (int) $facility['available_slots_count'] > 0;
    });

    return count($available);
}
