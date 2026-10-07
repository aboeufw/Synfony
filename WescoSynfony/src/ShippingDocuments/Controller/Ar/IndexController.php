<?php

/**
 * Controller for upload AR
 *
 * TEST : https://web.local/bl/LT783570/79140
 *
 * @Author: Antoine BOEUF <aboeuf@wesco.fr>
 * @create: 2025-09-26
 */

declare(strict_types=1);

namespace App\ShippingDocuments\Controller\Ar;

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

    #[Route('/shippingDocuments/ar/{id}/{track}/{type}', name: 'app_ar_index')]
    public function index(string $id, string $track, string $type = 'pdf'): Response
    {
        $order = $this->repository->getBl($id, $track);

        $version = str_replace(' ', '_', strtoupper(trim($order['FAC_PAYS'])));
        if ($order['FAC_PAYS'] == 'BELGIUM') {
            $version = $version . '_' . strtoupper(trim($order['CODELANGUE']));
        }

        $path = $this->getParameter('kernel.project_dir') . '/public/images/bonderetour_' . $version . '.png';
        $fond = base64_encode(file_get_contents($path));

        $html = $this->renderView('shipping_documents/ar.html.twig', [
            'order'         => $order,
            'fond'         => $fond,
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
                sprintf('AR-%s-%s.pdf', $order['COMMANDE'], time())
            );

            $response->headers->set(
                'Content-Disposition',
                'inline; filename="' . sprintf('AR-%s-%s.pdf', $order['COMMANDE'], time()) . '"'
            );

            return $response;
        }

        return $this->render('shipping_documents/ar.html.twig', [
            'controller_name'   => 'ar_IndexController',
            'order'             => $order,
            'fond'         => $fond,
        ]);
    }
}
