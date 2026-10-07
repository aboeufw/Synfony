<?php

namespace App\ShippingDocuments\Controller;

use App\ShippingDocuments\Repository\Bext\BextRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class IndexController extends AbstractController
{
    protected array $ArPays = [
        'FRANCE',
        'DEUTSCHLAND',
        'GERMANY',
        'NEDERLAND',
        'AUSTRIA',
        'IRELAND',
        'BELGIUM',
        'PORTUGAL',
        'THE NETHERLANDS',
    ];

    public function __construct(
        protected BextRepository $repository,
        protected TranslatorInterface $translator,
        #[Autowire(service: 'limiter.generate_endpoint')]
        protected RateLimiterFactory $rateLimiterFactory,
    ) {
    }

    #[Route('/shippingDocuments', name: 'app_shipping_documents_search')]
    public function search(): Response
    {
        return $this->render('shipping_documents/search.html.twig', [
            'controller_name'   => 'IndexController',
        ]);
    }

    #[Route('/shippingDocuments/{id}/{track}', name: 'app_shipping_documents_index')]
    public function index(string $id, string $track, Request $request): Response
    {
        $check = $this->check($request);
        if ($check !== true) {
            return $check;
        }

        $order = $this->repository->getBl($id, $track);
        $showAr = false;
        $codeLangue = 'fr';
        if ($order) {
            $codeLangue = trim($order['CODELANGUE']) != '' ? strtolower(trim($order['CODELANGUE'])) : strtolower(trim($order['CODELANGUE2']));
            $this->translator->setLocale($codeLangue);

            $showAr = $order['LIV_CRENEAU'] != 'LI' && in_array($order['FAC_PAYS'], $this->ArPays);

            $codeLangue = trim($order['CODELANGUE']) != '' ? strtolower(trim($order['CODELANGUE'])) : strtolower(trim($order['CODELANGUE2']));
        }

        return $this->render('shipping_documents/index.html.twig', [
            'controller_name'   => 'IndexController',
            'order'             => $order,
            'orderId'           => $id,
            'track'                => $track,
            'showAr'            => $showAr,
            'lang'            => $codeLangue,
        ]);
    }

    public function check(Request $request)
    {
        if ($request->getClientIp() == '90.80.183.245') {
            return true;
        }

        $key = $request->getClientIp() ?? 'unknown';
        $limiter = $this->rateLimiterFactory->create($key);

        $limit = $limiter->consume(1); // consomme 1 "jeton"
        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter(); // \DateTimeInterface
            $response = new Response('Too Many Requests', 429);
            if ($retryAfter) {
                $response->headers->set('Retry-After', (string) max(1, $retryAfter->getTimestamp() - time()));
            }
            return $response;
        }
        return true;
    }
}
