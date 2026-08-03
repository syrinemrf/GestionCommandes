<?php

namespace App\Service;

use App\Entity\Commande;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Environment;

class CommandeDocumentService
{
    private const LABEL_WIDTH_POINTS = 283.4646;
    private const LABEL_HEIGHT_POINTS = 425.1969;

    public function __construct(
        private Environment $twig,
    ) {
    }

    public function createBonLivraisonResponse(
        Commande $commande,
    ): Response {
        $this->assertDocumentsAvailable($commande);

        return $this->createPdfResponse(
            $this->twig->render(
                'commande/documents/bon_livraison.html.twig',
                ['commande' => $commande]
            ),
            'bon-livraison-' . $this->getReference($commande) . '.pdf',
            'A4'
        );
    }

    public function createEtiquetteColisResponse(
        Commande $commande,
    ): Response {
        $this->assertDocumentsAvailable($commande);

        return $this->createPdfResponse(
            $this->twig->render(
                'commande/documents/etiquette_colis.html.twig',
                ['commande' => $commande]
            ),
            'etiquette-colis-' . $this->getReference($commande) . '.pdf',
            [
                0,
                0,
                self::LABEL_WIDTH_POINTS,
                self::LABEL_HEIGHT_POINTS,
            ]
        );
    }

    private function assertDocumentsAvailable(Commande $commande): void
    {
        if (!$commande->canGenerateDocuments()) {
            throw new \DomainException(
                'Les documents sont indisponibles pour le statut actuel de la commande.'
            );
        }
    }

    private function createPdfResponse(
        string $html,
        string $filename,
        string|array $paper,
    ): Response {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paper, 'portrait');
        $dompdf->render();

        $response = new Response($dompdf->output());
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            HeaderUtils::makeDisposition(
                ResponseHeaderBag::DISPOSITION_INLINE,
                $filename
            )
        );
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function getReference(Commande $commande): string
    {
        return sprintf('CMD-%06d', $commande->getNumero());
    }
}
