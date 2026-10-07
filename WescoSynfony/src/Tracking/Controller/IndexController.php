<?php

namespace App\Tracking\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use App\Tracking\Repository\Bext\BextRepository;

final class IndexController extends AbstractController
{
    public function __construct(
        protected BextRepository $repository,
        #[Autowire('%env(SHIPUP_API_TOKEN)%')]
        protected string $shipupToken,
        #[Autowire('%env(QAD_API_USER)%')]
        protected string $qadUser,
        #[Autowire('%env(QAD_API_PASSWORD)%')]
        protected string $qadPassword,
    ) {
    }

    //#[Route('/{orderId}', name: 'app_tracking_index', host: 'tracking.local')]
    #[Route('/{orderId}', name: 'app_tracking_index')]
    public function index(string $orderId): Response
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            $qadTracking = $this->getQadTracking($orderId);
            if (!empty($qadTracking)) {

                $data = [];
                foreach ($qadTracking['data'] as $exp) {
                    $data[$exp['local_variables.local-var04']] = $exp['local_variables.local-var05'];
                }

                return $this->render('tracking/index.html.twig', [
                    'controller_name'   => 'IndexController',
                    'order'             => 'false',
                    'orderId'             => $orderId,
                    'dataExp'           => $data,
                ]);
            }
            return $this->render('tracking/index.html.twig', [
                'controller_name'   => 'IndexController',
                'order'             => 'false',
                'dataExp'           => 'false',
            ]);
        }

        $orderData = [
            'order_id' => $orderId,
            'created_at' => date('Y-m-d H:i:s', $order['ordered_at']),
        ];

        $tracking = [];
        $beforeTracking = [];
        $bextTracking = [];
        foreach ($order['fulfillments']['data'] as $fulfillment) {
            if (!empty($fulfillment['trackers']['data'])) {
                foreach ($fulfillment['trackers']['data'] as $tracker) {
                    $qty = 0;
                    foreach ($tracker['line_items']['data'] as $lineItem) {
                        $qty += $lineItem['quantity'];
                    }
                    $tracking[] = [
                        'tracking_number' => $tracker['tracking_number'],
                        'carrier_code' => $tracker['carrier']['code'],
                        'qty' => $qty,
                        'url' => 'https://wesco.shipup.co/?searchEnabled=false&trackingNumber=' . $tracker['tracking_number'],
                        'lt' => !empty($tracker['custom_variables'])
                            ? $tracker['custom_variables']['w_commande']
                            : '',
                        'ot' => !empty($tracker['custom_variables'])
                            ? $tracker['custom_variables']['w_ot']
                            : '',
                        'num_liv' => !empty($tracker['custom_variables'])
                            ? $tracker['custom_variables']['w_nolivraison']
                            : '',
                        'nb_colis' => !empty($tracker['custom_variables'])
                            ? $tracker['custom_variables']['w_nb_colis']
                            : '',
                        'shippingDocument' => !empty($tracker['custom_variables']) ? 'https://print.wesco-group.com/shippingDocuments/' . $tracker['custom_variables']['w_commande'] . '/' . $tracker['custom_variables']['w_nolivraison'] : '',
                        'shipped_at' => date('Y-m-d H:i:s', $tracker['shipped_at']),
                        'delivered_at' => $tracker['delivered_at'] == null ? 'false' : date('Y-m-d H:i:s', $tracker['delivered_at']),
                    ];
                }
            } else {
                $beforeTracking[] = [
                    'lt' => $fulfillment['merchant_id'],
                    'status_code' => $fulfillment['status_code'],
                ];

                // Il faut regarder si c'est un transporteur géré par Shipup
                $data = $this->getBextOrderTracking($fulfillment['merchant_id']);
                if ($data !== false) {
                    $data['STD_ETAT_CDE_LABELLE'] = $this->getBextStatus($data['STD_ETAT_CDE']);
                    $bextTracking[] = $data;
                    $beforeTracking = [];
                }
            }
        }

        return $this->render('tracking/index.html.twig', [
            'controller_name'   => 'IndexController',
            'order'             => $orderData,
            'trackers'          => $tracking,
            'beforeTrackers'    => $beforeTracking,
            'bextTrackers'      => $bextTracking,
        ]);
    }

    public function getOrder(string $order): array|bool
    {
        $url = "https://api.shipup.co/v2/orders?order_number=" . $order . "&expand[]=fulfillments.trackers.line_items&expand[]=fulfillments.trackers";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->shipupToken}",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        return isset($data['data'][0]) ? $data['data'][0] : false;
    }

    public function getQadTracking(string $orderId): array
    {
        $url = 'https://wesco-group.qad.com/clouderp/api/qracore/browses?browseId=urn:browse:mfg:xi004&filter=abs_mstr.abs_order,eq,' . $orderId . ',';
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt(
            $curl,
            CURLOPT_HTTPHEADER,
            [
                "Content-type: application/json",
                "Accept: application/json",
                "Accept-Language: fr",
                'Authorization: Basic ' . base64_encode($this->qadUser . ':' . $this->qadPassword),
            ]
        );
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($curl);
        return json_decode($response, true) ?? [];
    }

    public function getBextOrderTracking(string $orderId): bool|array
    {
        return $this->repository->getBextOrderTracking($orderId);
    }

    public function getBextStatus($id): string
    {
        $status = [
            0  => 'Attente',
            1  => 'Défaut',
            2  => 'Préparable',
            3  => 'Non Lançable',
            4  => 'Lancée',
            5  => 'En Cours',
            6  => 'Préparée',
            7  => 'Contrôlée',
            8  => 'Affectée',
            9  => 'Expédiée',
            10 => 'Stockée',
            11 => 'Exportée',
            12 => 'Regroupée',
            13 => 'Annulée',
        ];
        return $status[$id];
    }
}
