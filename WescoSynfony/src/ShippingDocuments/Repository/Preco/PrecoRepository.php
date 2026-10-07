<?php

/**
 * Preco repository
 *
 * @Author: Antoine BOEUF <aboeuf@wesco.fr>
 * @create: 2025-09-22
 */

declare(strict_types=1);

namespace App\ShippingDocuments\Repository\Preco;

use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

class PrecoRepository
{
    public function __construct(protected ManagerRegistry $doctrine) {}

    public function getColors(string $color): array|bool
    {
        $conn = $this->doctrine->getConnection('secondary');

        $sql = <<<SQL
                    SELECT *
                    FROM tbl_coloris
                    WHERE coloris_id = :color
        SQL;
        $row = $conn->executeQuery($sql, ['color' => $color], ['color' => ParameterType::STRING]);
        $colors = $row->fetchAssociative();

        if (!$colors) {
            return false;
        }

        $colors['coloris_libelle_du'] = $colors['coloris_libelle_nl'];
        $colors['coloris_libelle_cs'] = $colors['coloris_libelle_es'];
        $colors['coloris_libelle_us'] = $colors['coloris_libelle_en'];

        return $colors;
    }
}
