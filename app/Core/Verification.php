<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Verification
{
    public const DOCUMENT_TYPES = [
        'identidade_cpf_cnpj' => 'Documento de identificação / CPF ou CNPJ',
        'cartao_cnpj' => 'Cartão CNPJ',
        'certidao_federal' => 'Certidão federal',
        'registro_profissional' => 'Registro profissional (CREA/CAU/CRT etc.)',
        'nr_treinamento' => 'NR / treinamento obrigatório',
        'seguro' => 'Seguro',
        'licenca' => 'Licença / alvará / certificado',
        'outro' => 'Outro documento',
    ];

    public const IDENTITY_TYPES = ['identidade_cpf_cnpj', 'cartao_cnpj'];

    public static function hasValidApprovedIdentity(PDO $pdo, int $supplierUserId): bool
    {
        $placeholders = implode(',', array_fill(0, count(self::IDENTITY_TYPES), '?'));
        $sql = "SELECT 1
                FROM supplier_documents
                WHERE supplier_user_id = ?
                  AND document_type IN ($placeholders)
                  AND status = 'aprovado'
                  AND (expires_at IS NULL OR expires_at >= CURRENT_DATE)
                LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$supplierUserId], self::IDENTITY_TYPES));
        return (bool) $stmt->fetchColumn();
    }

    public static function refreshSeal(PDO $pdo, int $supplierUserId): void
    {
        if (!self::hasValidApprovedIdentity($pdo, $supplierUserId)) {
            $stmt = $pdo->prepare('UPDATE supplier_profiles SET verified_at = NULL WHERE user_id = :user_id AND verified_at IS NOT NULL');
            $stmt->execute(['user_id' => $supplierUserId]);
        }
    }

    public static function grantSeal(PDO $pdo, int $supplierUserId, int $adminUserId, ?string $notes = null): bool
    {
        if (!self::hasValidApprovedIdentity($pdo, $supplierUserId)) {
            return false;
        }

        $stmt = $pdo->prepare('UPDATE supplier_profiles
            SET verified_at = CURRENT_TIMESTAMP,
                verification_notes = :notes,
                verification_reviewed_at = CURRENT_TIMESTAMP,
                verification_reviewed_by_user_id = :admin_id
            WHERE user_id = :user_id');
        $stmt->execute([
            'notes' => $notes !== '' ? $notes : null,
            'admin_id' => $adminUserId,
            'user_id' => $supplierUserId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public static function revokeSeal(PDO $pdo, int $supplierUserId, int $adminUserId, ?string $notes = null): void
    {
        $stmt = $pdo->prepare('UPDATE supplier_profiles
            SET verified_at = NULL,
                verification_notes = :notes,
                verification_reviewed_at = CURRENT_TIMESTAMP,
                verification_reviewed_by_user_id = :admin_id
            WHERE user_id = :user_id');
        $stmt->execute([
            'notes' => $notes !== '' ? $notes : null,
            'admin_id' => $adminUserId,
            'user_id' => $supplierUserId,
        ]);
    }
}
