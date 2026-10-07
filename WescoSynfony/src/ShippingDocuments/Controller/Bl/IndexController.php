<?php

/**
 * Controller for upload BL
 *
 * TEST : https://web.local/bl/LT783570/79140
 *
 * @Author: Antoine BOEUF <aboeuf@wesco.fr>
 * @create: 2025-09-26
 */

declare(strict_types=1);

namespace App\ShippingDocuments\Controller\Bl;

use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use App\ShippingDocuments\Repository\Bext\BextRepository;
use App\ShippingDocuments\Repository\Preco\PrecoRepository;
use Knp\Snappy\Pdf;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IndexController extends AbstractController
{
    public function __construct(
        protected BextRepository $repository,
        protected PrecoRepository $precoRepository,
        protected Pdf $pdf,
        protected TranslatorInterface $translator,
    ) {
    }

    #[Route('/shippingDocuments/bl/load/{id}/{track}/{lang}/{type}', name: 'app_bl_index', methods: ['GET'])]
    public function shell(string $id, string $track, string $lang, string $type = 'pdf'): Response
    {
        $path = $this->getParameter('kernel.project_dir').'/public/images/wesco_loading.gif';
        $loadingBase64 = base64_encode(file_get_contents($path));

        $this->translator->setLocale($lang);

        return $this->render('shipping_documents/bl_load.html.twig', [
            'id' => $id,
            'track' => $track,
            'loadingBase64' => $loadingBase64,
            'type'          => $type,
        ]);
    }

    #[Route('/shippingDocuments/bl/{id}/{track}/{type}/fragment', name: 'app_bl_index_fragment', methods: ['GET'])]
    public function index(string $id, string $track, string $type = 'pdf'): Response
    {
        $order = $this->repository->getBl($id, $track);
        $lines = $this->repository->getBlLines($id);

        $kitOfBl = $this->getKitComposition($lines);

        $codeLangue = trim($order['CODELANGUE']) != '' ? strtolower(trim($order['CODELANGUE'])) : strtolower(trim($order['CODELANGUE2']));
        $this->translator->setLocale($codeLangue);

        $path = $this->getParameter('kernel.project_dir').'/public/images/logo.png';
        $logoBase64 = base64_encode(file_get_contents($path));

        $order['showBilling'] = 'true';
        // Si HERMEX, on n'affiche pas l'adresse de facturation
        // SI MORLEY (AA015000) on n'affiche pas l'adresse de facturation
        if (
            (str_contains($order['FAC_NOM1'], "HERMEX") && $order['FAC_PAYS'] == 'SPAIN')
            || $order['FAC_NUMCLIENT'] == 'AA015000'
        ) {
            $order['showBilling'] = 'false';
        }

        $date = new DateTime($order['DATEPREP']);
        $order['DATEPREP'] = $date->format('d/m/Y');

        $linesSimple = [];
        $linesCompose = [];
        $order['haveReliquat'] = 'false';
        foreach ($lines as $line) {
            $line['SKU_FORMAT'] = substr($line['STD_REFERENCE'], 0, -3);

            // Si LIT
            if (trim($line['SKU_OF_KIT']) != '') {
                // on chercher le nombre de composant pour 1 kit
                $line['nbCompose'] = $kitOfBl[$line['SKU_OF_KIT'] . '-' . $line['STD_REFERENCE']]['qty'];

                // on divise le reliquat pas le nombre de composant dans le kit pour avoir le reliquat du kit fini
                $line['QTE_RESTE_A_LIVRER_FINAL'] /= $line['nbCompose'];
            }

            if ($line['QTE_RESTE_A_LIVRER_FINAL'] > 0) {
                $order['haveReliquat'] = 'true';
            }

            $color = $this->precoRepository->getColors(substr($line['STD_REFERENCE'], -3));

            $line['COLORS'] =  $color ? $color['coloris_libelle_' . $codeLangue] : '';

            if (trim($line['SKU_OF_KIT']) != '') {
                if (trim($line['ARTCLI']) == '') {
                    $line['SKU_FORMAT_PARENT'] = substr($line['SKU_OF_KIT'], 0, -3);
                } else {
                    $line['SKU_FORMAT_PARENT'] = $line['ARTCLI'];
                }

                $colorParent = $this->precoRepository->getColors(substr($line['SKU_OF_KIT'], -3));

                $line['COLOR_PARENT'] = $colorParent ? $colorParent['coloris_libelle_' . $codeLangue] : '';
                $linesCompose[$line['SKU_OF_KIT']][] = $line;
                continue;
            } else {
                if (trim($line['ARTCLI']) != '') {
                    $line['SKU_FORMAT'] = $line['ARTCLI'];
                }
            }
            $linesSimple[] = $line;
        }

        $html = $this->renderView('shipping_documents/bl.html.twig', [
            'order'         => $order,
            'linesSimple'   => $linesSimple,
            'linesCompose'  => $linesCompose,
            'logoBase64'    => $logoBase64,
        ]);

        $css = $this->getParameter('kernel.project_dir').'/public/css/shipping_documents/bl.css';
        $options = [
            'footer-right'      => 'P. [page]/[toPage]',
            'footer-font-size'  => 9,
            'print-media-type'  => true,
            'user-style-sheet'  => $css,
            'margin-bottom'     => '10mm',
        ];

        if ($type == 'pdf') {
            $response = new PdfResponse(
                $this->pdf->getOutputFromHtml($html, $options),
                sprintf('BL-%s-%s.pdf', $order['COMMANDE'], time())
            );

            $response->headers->set(
                'Content-Disposition',
                'inline; filename="' . sprintf('BL-%s-%s.pdf', $order['COMMANDE'], time()) . '"'
            );

            return $response;
        }

        return $this->render('shipping_documents/bl.html.twig', [
            'controller_name'   => 'bl_IndexController',
            'order'             => $order,
            'linesSimple'       => $linesSimple,
            'linesCompose'      => $linesCompose,
            'logoBase64'        => $logoBase64,
        ]);
    }

    /**
     * Get Qty of component for the kit
     */
    protected function getKitComposition(array $lines): array
    {
        $kitOfBl = [];

        // Si un des composants est à 1 alors la quantité de kit est à 1
        foreach ($lines as $line) {
            if (trim($line['SKU_OF_KIT']) != '') {
                if ($line['QTECDEE'] == 1) {
                    $kitOfBl[$line['SKU_OF_KIT'] . '-' . $line['STD_REFERENCE']] = [
                        'qty' => 1,
                        'SKU_OF_KIT' => $line['SKU_OF_KIT'],
                        'STD_REFERENCE' => $line['STD_REFERENCE'],
                    ];
                } elseif (!isset($kitOfBl[$line['SKU_OF_KIT'] . '-' . $line['STD_REFERENCE']])) {
                    $kitOfBl[$line['SKU_OF_KIT'] . '-' . $line['STD_REFERENCE']] = [
                        'qty' => 0,
                        'SKU_OF_KIT' => $line['SKU_OF_KIT'],
                        'STD_REFERENCE' => $line['STD_REFERENCE'],
                    ];
                }
            }
        }

        // Si la qty du composant est > 1 on va chercher une commande complete
        // SI on ne trouve rien on met 1
        foreach ($kitOfBl as $key => $data) {
            if ($data['qty'] == 0) {
                $kitOfBl[$key] = [
                    'qty' => round((float) $this->repository->getKitComp($data['SKU_OF_KIT'], $data['STD_REFERENCE'])) == 0 ? 1 : round((float) $this->repository->getKitComp($data['SKU_OF_KIT'], $data['STD_REFERENCE'])),
                    'SKU_OF_KIT' => $data['SKU_OF_KIT'],
                    'STD_REFERENCE' => $data['STD_REFERENCE'],
                ];
            }
        }

        return $kitOfBl;
    }
}
