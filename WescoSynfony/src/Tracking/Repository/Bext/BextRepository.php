<?php

/**
 * Bext repository
 *
 * @Author: Antoine BOEUF <aboeuf@wesco.fr>
 * @create: 2026-03-25
 */

declare(strict_types=1);

namespace App\Tracking\Repository\Bext;

use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

class BextRepository
{
    public function __construct(protected ManagerRegistry $doctrine) {}

    public function getBextOrderTracking(string $order): bool|array
    {
        $conn = $this->doctrine->getConnection('primary');

        $sql = <<<SQL
                    SELECT CODETRANS_WS, STD_DEXPED_CDE, STD_NOMTRANS, STD_ETAT_CDE, STD_COMMANDE
                    FROM STD_COMMANDES
                    WHERE STD_COMMANDES.STD_COMMANDE = :order
                      AND STD_COMMANDES.STD_CODETRANS IN ('LER', 'TRP')
        SQL;

        return $conn->executeQuery(
            $sql,
            ['order' => $order,],
            ['order' => ParameterType::STRING]
        )->fetchAssociative();
    }
}
