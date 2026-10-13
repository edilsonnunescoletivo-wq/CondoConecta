<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Marketplace
{
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));
        return $earthRadius * $c;
    }

    public static function matchingSuppliers(
        PDO $pdo,
        int $categoryId,
        string $city,
        string $state,
        string $urgency,
        ?float $latitude = null,
        ?float $longitude = null
    ): array {
        $sql = "SELECT DISTINCT sp.id, sp.user_id, sp.trade_name, sp.city, sp.state,
                               sp.service_radius_km, sp.emergency_service, sp.latitude, sp.longitude,
                               EXISTS (
                                   SELECT 1 FROM supplier_regions sreg
                                   WHERE sreg.supplier_id = sp.id
                                     AND sreg.state = :region_state
                                     AND sreg.city = :region_city
                               ) AS region_match
                FROM supplier_profiles sp
                INNER JOIN users u ON u.id = sp.user_id AND u.active = 1
                INNER JOIN supplier_categories sc ON sc.supplier_id = sp.id AND sc.category_id = :category_id
                WHERE (:urgency_check <> 'agora' OR sp.emergency_service = 1)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'region_state' => $state,
            'region_city' => $city,
            'category_id' => $categoryId,
            'urgency_check' => $urgency,
        ]);

        $matches = [];
        foreach ($stmt->fetchAll() as $supplier) {
            $supplierLat = $supplier['latitude'] !== null ? (float) $supplier['latitude'] : null;
            $supplierLng = $supplier['longitude'] !== null ? (float) $supplier['longitude'] : null;
            $distance = null;
            $matched = false;

            if ($latitude !== null && $longitude !== null && $supplierLat !== null && $supplierLng !== null) {
                $distance = self::distanceKm($latitude, $longitude, $supplierLat, $supplierLng);
                $matched = $distance <= (float) $supplier['service_radius_km'];
            } else {
                $baseMatch = strcasecmp((string) $supplier['state'], $state) === 0
                    && strcasecmp(trim((string) $supplier['city']), trim($city)) === 0;
                $matched = $baseMatch || (bool) $supplier['region_match'];
            }

            if (!$matched) {
                continue;
            }

            $supplier['distance_km'] = $distance;
            $matches[] = $supplier;
        }

        usort($matches, static function (array $a, array $b): int {
            $aDistance = $a['distance_km'];
            $bDistance = $b['distance_km'];
            if ($aDistance === null && $bDistance !== null) return 1;
            if ($aDistance !== null && $bDistance === null) return -1;
            if ($aDistance !== null && $bDistance !== null) {
                $cmp = $aDistance <=> $bDistance;
                if ($cmp !== 0) return $cmp;
            }
            return strcasecmp((string) $a['trade_name'], (string) $b['trade_name']);
        });

        return $matches;
    }

    public static function matchingSuppliersForRequest(PDO $pdo, int $requestId): array
    {
        $stmt = $pdo->prepare('SELECT category_id, city, state, urgency, latitude, longitude FROM service_requests WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $requestId]);
        $request = $stmt->fetch();
        if (!$request) {
            return [];
        }

        return self::matchingSuppliers(
            $pdo,
            (int) $request['category_id'],
            (string) $request['city'],
            (string) $request['state'],
            (string) $request['urgency'],
            $request['latitude'] !== null ? (float) $request['latitude'] : null,
            $request['longitude'] !== null ? (float) $request['longitude'] : null
        );
    }

    public static function countMatchingSuppliers(
        PDO $pdo,
        int $categoryId,
        string $city,
        string $state,
        string $urgency,
        ?float $latitude = null,
        ?float $longitude = null
    ): int {
        return count(self::matchingSuppliers($pdo, $categoryId, $city, $state, $urgency, $latitude, $longitude));
    }

    public static function supplierOpportunityMatches(PDO $pdo, int $supplierUserId): array
    {
        $profileStmt = $pdo->prepare('SELECT id, city, state, service_radius_km, emergency_service, latitude, longitude FROM supplier_profiles WHERE user_id = :user_id LIMIT 1');
        $profileStmt->execute(['user_id' => $supplierUserId]);
        $supplier = $profileStmt->fetch();
        if (!$supplier) {
            return [];
        }

        $sql = "SELECT DISTINCT sr.id, sr.city, sr.state, sr.urgency, sr.latitude, sr.longitude,
                               EXISTS (
                                   SELECT 1 FROM supplier_regions sreg
                                   WHERE sreg.supplier_id = :supplier_id_region
                                     AND sreg.state = sr.state
                                     AND sreg.city = sr.city
                               ) AS region_match
                FROM service_requests sr
                INNER JOIN supplier_categories sc ON sc.supplier_id = :supplier_id_category AND sc.category_id = sr.category_id
                WHERE sr.status IN ('aberta','em_cotacao')
                  AND (sr.urgency <> 'agora' OR :emergency = 1)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'supplier_id_region' => (int) $supplier['id'],
            'supplier_id_category' => (int) $supplier['id'],
            'emergency' => (int) $supplier['emergency_service'],
        ]);

        $matches = [];
        foreach ($stmt->fetchAll() as $request) {
            $requestLat = $request['latitude'] !== null ? (float) $request['latitude'] : null;
            $requestLng = $request['longitude'] !== null ? (float) $request['longitude'] : null;
            $supplierLat = $supplier['latitude'] !== null ? (float) $supplier['latitude'] : null;
            $supplierLng = $supplier['longitude'] !== null ? (float) $supplier['longitude'] : null;
            $distance = null;
            $matched = false;

            if ($requestLat !== null && $requestLng !== null && $supplierLat !== null && $supplierLng !== null) {
                $distance = self::distanceKm($requestLat, $requestLng, $supplierLat, $supplierLng);
                $matched = $distance <= (float) $supplier['service_radius_km'];
            } else {
                $baseMatch = strcasecmp((string) $supplier['state'], (string) $request['state']) === 0
                    && strcasecmp(trim((string) $supplier['city']), trim((string) $request['city'])) === 0;
                $matched = $baseMatch || (bool) $request['region_match'];
            }

            if ($matched) {
                $matches[(int) $request['id']] = $distance;
            }
        }

        return $matches;
    }

    public static function supplierCanAccessRequest(PDO $pdo, int $supplierUserId, int $requestId): bool
    {
        return array_key_exists($requestId, self::supplierOpportunityMatches($pdo, $supplierUserId));
    }

    public static function supplierOpportunityCount(PDO $pdo, int $supplierUserId): int
    {
        return count(self::supplierOpportunityMatches($pdo, $supplierUserId));
    }
}
