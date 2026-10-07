<?php

/**
 * Controller for upload Lc
 *
 * TEST : https://web.local/bl/LT783570/79140
 *
 * @Author: Antoine BOEUF <aboeuf@wesco.fr>
 * @create: 2025-09-26
 */

declare(strict_types=1);

namespace App\ShippingDocuments\Controller\Lc;

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

    #[Route('/shippingDocuments/lc/load/{id}/{track}/{lang}/{type}', name: 'app_lc_index', methods: ['GET'])]
    public function shell(string $id, string $track, string $lang, string $type = 'pdf'): Response
    {
        $path = $this->getParameter('kernel.project_dir').'/public/images/wesco_loading.gif';
        $loadingBase64 = base64_encode(file_get_contents($path));

        $this->translator->setLocale($lang);

        return $this->render('shipping_documents/lc_load.html.twig', [
            'id' => $id,
            'track' => $track,
            'loadingBase64' => $loadingBase64,
            'type'          => $type,
        ]);
    }

    #[Route('/shippingDocuments/lc/{id}/{track}/{type}/fragment', name: 'app_lc_index_fragment')]
    public function index(string $id, string $track, string $type = 'pdf'): Response
    {
        $order = $this->repository->getBl($id, $track);

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

        $lines = $this->repository->getLC($id);
        $colis = [];
        $num = 1;
        foreach ($lines as $line) {
            $color = $this->precoRepository->getColors(substr($line['STD_REFERENCE'], -3));

            $line['COLORS'] =  $color ? $color['coloris_libelle_' . $codeLangue] : '';

            if (!isset($colis[$line['COLIS']])) {
                $colis[$line['COLIS']] = [
                    'class' => $line['W_CLASSE'],
                    'num' => $num,
                    'lines' => [],
                ];
                $num++;
            }

            $line['STD_REFERENCE'] = substr($line['STD_REFERENCE'], 0, -3);

            $colis[$line['COLIS']]['lines'][] = $line;
        }

        foreach ($colis as &$c) {
            usort($c['lines'], function ($a, $b) {
                return $a['LIGNECOLIS'] <=> $b['LIGNECOLIS'];
            });
        }

        $html = $this->renderView('shipping_documents/lc.html.twig', [
            'order'         => $order,
            'colis'         => $colis,
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
                sprintf('LC-%s-%s.pdf', $order['COMMANDE'], time())
            );

            $response->headers->set(
                'Content-Disposition',
                'inline; filename="' . sprintf('LC-%s-%s.pdf', $order['COMMANDE'], time()) . '"'
            );

            return $response;
        }


        return $this->render('shipping_documents/lc.html.twig', [
            'controller_name'   => 'lc_IndexController',
            'order'             => $order,
            'colis'         => $colis,
            'logoBase64'        => $logoBase64,
        ]);
    }
}
