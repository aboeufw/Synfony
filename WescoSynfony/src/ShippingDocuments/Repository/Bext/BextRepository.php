<?php

/**
 * Bext repository
 *
 * @Author: Antoine BOEUF <aboeuf@wesco.fr>
 * @create: 2025-09-26
 */

declare(strict_types=1);

namespace App\ShippingDocuments\Repository\Bext;

use Doctrine\DBAL\ParameterType;
use Doctrine\Persistence\ManagerRegistry;

class BextRepository
{
    public function __construct(protected ManagerRegistry $doctrine) {}

    public function getBl(string $order, string $track): bool|array
    {
        $conn = $this->doctrine->getConnection('primary');

        $sql = <<<SQL
                    SELECT DISTINCT STD_COMMANDES.STD_COMMANDE as COMMANDE,
                                    STD_COMMANDES.STD_NOMCLIENT_LIV as LIV_NOM1,
                                    STD_COMMANDES.STD_ADRESSE1_LIV as LIV_ADRESSE1,
                                    STD_COMMANDES.STD_ADRESSE2_LIV as LIV_ADRESSE2,
                                    STD_COMMANDES.STD_CODEPOSTAL_LIV as LIV_CODEPOSTAL,
                                    STD_COMMANDES.STD_VILLE_LIV as LIV_VILLE,
                                    STD_COMMANDES.STD_PAYS_LIV as LIV_PAYS,
                                    STD_COMMANDES.STD_NBCOLIS_CDE as NBCOLIS,
                                    STD_COMMANDES.STD_POIDS_CDE as POIDS,
                                    STD_COMMANDES.STD_VOLUME_CDE as VOLUME,
                                    STD_COMMANDES.STD_DPREP_CDE as DATEPREP,
                                    STD_COMMANDES.STD_COMMANDE_N3 as GESCOM,
                                    STD_COMMANDES.STD_COMMANDE_CLIENT as CMDECLIENT,
                                    STD_COMMANDES.EXP_NOM1 as EXP_NOM1,
                                    STD_COMMANDES.SPE_INFORMATION_LIVRAISON_1 as LIBELLELIV1,
                                    STD_COMMANDES.EXP_ADRESSE1 as EXP_ADRESSE1,
                                    STD_COMMANDES.EXP_ADRESSE2 as EXP_ADRESSE2,
                                    STD_COMMANDES.EXP_ADRESSE3 as EXP_ADRESSE3,
                                    STD_COMMANDES.EXP_CODEPOSTAL as EXP_CODEPOSTAL,
                                    STD_COMMANDES.EXP_VILLE as EXP_VILLE,
                                    STD_COMMANDES.EXP_LIB_ETAT as EXP_LIB_ETAT,
                                    STD_COMMANDES.EXP_PAYS as EXP_PAYS,
                                    STD_COMMANDES.STD_CODE_TIERS as LIV_NUMCLIENT,
                                    STD_COMMANDES.STD_CODECLIENT_FACT as FAC_NUMCLIENT,
                                    STD_COMMANDES.STD_NOMCLIENT_FACT as FAC_NOM1,
                                    STD_COMMANDES.STD_ADRESSE1_FACT as FAC_ADRESSE1,
                                    STD_COMMANDES.STD_ADRESSE2_FACT as FAC_ADRESSE2,
                                    STD_COMMANDES.STD_ADRESSE3_FACT as FAC_ADRESSE3,
                                    STD_COMMANDES.STD_CODEPOSTAL_FACT as FAC_CODEPOSTAL,
                                    STD_COMMANDES.STD_VILLE_FACT as FAC_VILLE,
                                    STD_COMMANDES.FAC_LIB_ETAT as FAC_LIB_ETAT,
                                    STD_COMMANDES.STD_PAYS_FACT as FAC_PAYS,
                                    STD_COMMANDES.CODELANGUE as CODELANGUE,
                                    STD_COMMANDES.STD_CODE_LANGUE as CODELANGUE2,
                                    STD_COMMANDES.STD_NOLIVRAISON as NOLIVRAISON,
                                    STD_COMMANDES.W_NOMSOCIETE1 + ' ' + STD_COMMANDES.W_NOMSOCIETE2  as LIV_NOM2,
                                    STD_COMMANDES.STD_NOMTRANS as TRANSPORTEUR,
                                    STD_COMMANDES.LIV_CRENEAU as LIV_CRENEAU
                    FROM
                        STD_COLIS
                            left outer join STD_COMMANDES
                                on (STD_COLIS.STD_COMMANDE = STD_COMMANDES.STD_COMMANDE)
                            left outer join VRP
                                on (VRP.CODEVRP = STD_COMMANDES.CODEVRP)
                    WHERE STD_COMMANDES.STD_COMMANDE = :order
                      AND STD_COMMANDES.STD_NOLIVRAISON = :track
        SQL;

        return $conn->executeQuery(
            $sql,
            ['order' => $order, 'track' => $track],
            ['order' => ParameterType::STRING, 'track' => ParameterType::STRING]
        )->fetchAssociative();
    }

    public function getBlLines(string $order): array
    {
        $conn = $this->doctrine->getConnection('primary');
        $sql = <<<SQL
                    SELECT DISTINCT STD_COMMANDES_LIG.DESIGNATION1_IMP as product_name,
                                    STD_COMMANDES_LIG.LIBELLE1COMPOSE as configurable_product_name,
                                    STD_COMMANDES_LIG.QTECDEE as QTECDEE,
                                    STD_COMMANDES_LIG.STD_QTEPREP_LIGCDE as PREPAREE,
                                    STD_COMMANDES_LIG.QTEPREPKIT as QTEPREPKIT,
                                    STD_COMMANDES_LIG.CODECOMPOSE as SKU_OF_KIT,
                                    STD_COMMANDES_LIG.STD_REFERENCE,
                                    STD_COMMANDES_LIG.ARTCLI,
                                    STD_COMMANDES_LIG.STD_LIGCDE_N3 as LIGGESCOM,
                                    STD_COMMANDES_LIG.STD_QTECDEE_LIGCDE - STD_COMMANDES_LIG.STD_QTEPREP_LIGCDE + STD_COMMANDES_LIG.QTE_RESTE_A_LIVRER as QTE_RESTE_A_LIVRER_FINAL
                    FROM STD_COMMANDES_LIG
                    WHERE STD_COMMANDES_LIG.STD_COMMANDE = :order

        SQL;

        return $conn->executeQuery(
            $sql,
            ['order' => $order],
            ['order' => ParameterType::STRING]
        )->fetchAllAssociative();
    }

    public function getKitComp(mixed $parentSku, mixed $childSku): mixed
    {
        $conn = $this->doctrine->getConnection('primary');
        $sql = <<<SQL
                    SELECT TOP 1 STD_COMMANDES_LIG.QTECDEE / STD_COMMANDES_LIG.QTEPREPKIT as QTY_OF_COMP
                    FROM STD_COMMANDES_LIG
                    WHERE (STD_COMMANDES_LIG.STD_QTEPREP_LIGCDE > 0)
                    AND (STD_COMMANDES_LIG.QTEPREPKIT > 0)
                    AND (RTRIM(LTRIM(STD_COMMANDES_LIG.CODECOMPOSE)))= :parentSku
                    AND (RTRIM(LTRIM(STD_COMMANDES_LIG.STD_REFERENCE)))= :childSku
                    AND STD_ETAT_LIGCDE = 9
                    AND STD_SITE = 1
                    AND CAST(STD_COMMANDES_LIG.STD_DEXPED_LIGCDE AS DATE) >= GETDATE() - 180
        SQL;
        $result = $conn->executeQuery(
            $sql,
            ['parentSku' => $parentSku, 'childSku' => $childSku]
        )->fetchAssociative();

        return isset($result['QTY_OF_COMP']) ? $result['QTY_OF_COMP'] : 0;
    }

    public function getLC(mixed $order) :array
    {
        $conn = $this->doctrine->getConnection('primary');
        $sql = <<<SQL
                    SELECT
                         STD_COMMANDES_LIG.STD_COMMANDE as COMMANDE
                        ,  STD_COMMANDES_LIG.STD_DESIGNATION as LIBELLE1
                          , case
                         when STD_COLIS_LIG.STD_QTE_PREP_LIGCOLIS is null then 0
                         else STD_COLIS_LIG.STD_QTE_PREP_LIGCOLIS
                        end as PREPAREE
                        , STD_COMMANDES_LIG.CODECOMPOSE as CODECOMPOSE
                        , STD_COMMANDES_LIG.DESIGNATION1_IMP as product_name
                        , STD_COMMANDES_LIG.LIBELLE1COMPOSE as configurable_product_name
                        , STD_COMMANDES_LIG.STD_REFERENCE
                        , STD_COMMANDES_LIG.ARTCLI as ARTCLI
                        , STD_COLIS_LIG.STD_COLIS as COLIS --NUM COLIS
                        , case
                            when STD_COLIS_LIG.STD_LIG_COL is null then 0
                            else STD_COLIS_LIG.STD_LIG_COL
                          end as LIGNECOLIS -- TRI ?
                        , STD_COMMANDES_LIG.STD_LIGCDE_N3 as LIGGESCOM
                        , W_CLASSE
                    FROM
                        STD_COMMANDES_LIG left outer join STD_COLIS_LIG on (STD_COLIS_LIG.STD_COMMANDE = STD_COMMANDES_LIG.STD_COMMANDE AND LTRIM(STD_COLIS_LIG.STD_LIG_CDE)=LTRIM(STD_COMMANDES_LIG.STD_LIG_CDE) )
                        left outer join STD_COLIS on (STD_COLIS.STD_COLIS = STD_COLIS_LIG.STD_COLIS)
                    WHERE
                        STD_COMMANDES_LIG.STD_COMMANDE= :order
                        AND STD_QTEPREP_LIGCDE > 0
                        order by  STD_TRACKING1
            SQL;
        return $conn->executeQuery(
            $sql,
            ['order' => $order]
        )->fetchAllAssociative();
    }
}
