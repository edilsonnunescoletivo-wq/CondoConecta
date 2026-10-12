<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Marketplace
{
    public static function countMatchingSuppliers(PDO $pdo, int $categoryId, string $city, string $state, string $urgency): int
    {
        $sql = "SELECT COUNT(DISTINCT sp.id)
                FROM supplier_profiles sp
                INNER JOIN users u ON u.id = sp.user_id AND u.active = 1
                INNER JOIN supplier_categories sc ON sc.supplier_id = sp.id AND sc.category_id = :category_id
                WHERE (
                    (sp.state = :state_base AND sp.city = :city_base)
                    OR EXISTS (
                        SELECT 1 FROM supplier_regions sreg
                        WHERE sreg.supplier_id = sp.id
                          AND sreg.state = :state_region
                          AND sreg.city = :city_region
                    )
                )
                AND (:urgency_check <> 'agora' OR sp.emergency_service = 1)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'category_id' => $categoryId,
            'state_base' => $state,
            'city_base' => $city,
            'state_region' => $state,
            'city_region' => $city,
            'urgency_check' => $urgency,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public static function supplierCanAccessRequest(PDO $pdo, int $supplierUserId, int $requestId): bool
    {
        $sql = "SELECT 1
                FROM service_requests sr
                INNER JOIN supplier_profiles sp ON sp.user_id = :supplier_user_id
                INNER JOIN supplier_categories sc ON sc.supplier_id = sp.id AND sc.category_id = sr.category_id
                WHERE sr.id = :request_id
                  AND sr.status IN ('aberta','em_cotacao')
                  AND (
                    (sp.state = sr.state AND sp.city = sr.city)
                    OR EXISTS (
                        SELECT 1 FROM supplier_regions sreg
                        WHERE sreg.supplier_id = sp.id
                          AND sreg.state = sr.state
                          AND sreg.city = sr.city
                    )
                  )
                  AND (sr.urgency <> 'agora' OR sp.emergency_service = 1)
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'supplier_user_id' => $supplierUserId,
            'request_id' => $requestId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public static function supplierOpportunityCount(PDO $pdo, int $supplierUserId): int
    {
        $sql = "SELECT COUNT(DISTINCT sr.id)
                FROM service_requests sr
                INNER JOIN supplier_profiles sp ON sp.user_id = :supplier_user_id
                INNER JOIN supplier_categories sc ON sc.supplier_id = sp.id AND sc.category_id = sr.category_id
                WHERE sr.status IN ('aberta','em_cotacao')
                  AND (
                    (sp.state = sr.state AND sp.city = sr.city)
                    OR EXISTS (
                        SELECT 1 FROM supplier_regions sreg
                        WHERE sreg.supplier_id = sp.id
                          AND sreg.state = sr.state
                          AND sreg.city = sr.city
                    )
                  )
                  AND (sr.urgency <> 'agora' OR sp.emergency_service = 1)";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(['supplier_user_id' => $supplierUserId]);
        return (int) $stmt->fetchColumn();
    }
}
