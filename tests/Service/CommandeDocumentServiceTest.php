<?php

namespace App\Tests\Service;

use App\Entity\Client;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Entity\User;
use App\Service\CommandeDocumentService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Twig\Environment;

final class CommandeDocumentServiceTest extends KernelTestCase
{
    private CommandeDocumentService $service;
    private Environment $twig;

    protected function setUp(): void
    {
        self::bootKernel();

        $service = static::getContainer()->get(CommandeDocumentService::class);
        $twig = static::getContainer()->get(Environment::class);
        self::assertInstanceOf(CommandeDocumentService::class, $service);
        self::assertInstanceOf(Environment::class, $twig);
        $this->service = $service;
        $this->twig = $twig;
    }

    public function testLeBonDeLivraisonEstRetourneCommePdfInline(): void
    {
        $commande = $this->createCommande();

        $response = $this->service
            ->createBonLivraisonResponse($commande);

        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString(
            ResponseHeaderBag::DISPOSITION_INLINE,
            (string) $response->headers->get('Content-Disposition')
        );
        self::assertStringContainsString(
            'bon-livraison-CMD-000123.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
        self::assertStringStartsWith('%PDF-', (string) $response->getContent());
    }

    public function testEtiquetteColisEstRetourneeCommePdfInline(): void
    {
        $response = $this->service
            ->createEtiquetteColisResponse($this->createCommande());
        $content = (string) $response->getContent();

        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertStringContainsString(
            'etiquette-colis-CMD-000123.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
        self::assertStringStartsWith('%PDF-', $content);
        self::assertSame(
            1,
            preg_match_all('/\/Type\s*\/Page\b/', $content),
            'L’étiquette doit tenir sur une seule page.'
        );
        self::assertMatchesRegularExpression(
            '/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+283\.46\d*\s+425\.19\d*\s*\]/',
            $content,
            'Le PDF doit mesurer 100 × 150 mm.'
        );
    }

    public function testLesTemplatesNeContiennentPasDePrix(): void
    {
        $commande = $this->createCommande();
        $bonLivraison = $this->twig->render(
            'commande/documents/bon_livraison.html.twig',
            ['commande' => $commande]
        );
        $etiquette = $this->twig->render(
            'commande/documents/etiquette_colis.html.twig',
            ['commande' => $commande]
        );

        self::assertStringNotContainsString('99,999', $bonLivraison);
        self::assertStringNotContainsString('99.999', $bonLivraison);
        self::assertStringNotContainsString('Produit confidentiel', $etiquette);
        self::assertStringNotContainsString('99.999', $etiquette);
    }

    public function testUnStatutNonAutoriseBloqueLeDocument(): void
    {
        $commande = $this->createCommande()
            ->setStatut(Commande::STATUT_EN_PREPARATION);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('statut actuel');

        $this->service->createBonLivraisonResponse($commande);
    }

    private function createCommande(): Commande
    {
        $fournisseur = (new User())
            ->setNom('Ben Salah')
            ->setPrenom('Amine')
            ->setEmail('contact@fournisseur.test')
            ->setLibelle('Fournisseur Test');
        $client = (new Client())
            ->setPrenom('Sarra')
            ->setNom('Trabelsi')
            ->setTelephone('20 000 000')
            ->setRue('10 rue des Jasmins')
            ->setCodePostal('1000')
            ->setVille('Tunis');
        $ligne = (new LigneCommande())
            ->setNomProduit('Produit confidentiel')
            ->setNomVariation('Taille M')
            ->setQuantite(2)
            ->setPrixUnitaire('99.999');

        return (new Commande())
            ->setNumero(123)
            ->setDate(new \DateTimeImmutable('2026-08-03 10:00:00'))
            ->setStatut(Commande::STATUT_PRETE)
            ->setFournisseur($fournisseur)
            ->setClient($client)
            ->setNote('Livrer avant midi.')
            ->addLigne($ligne);
    }
}
