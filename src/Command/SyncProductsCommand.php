<?php

declare(strict_types=1);

namespace Odiseo\SyliusMailchimpPlugin\Command;

use Odiseo\SyliusMailchimpPlugin\Handler\ProductRegisterHandlerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncProductsCommand extends Command
{
    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var ProductRegisterHandlerInterface
     */
    protected $productRegisterHandler;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ProductRegisterHandlerInterface $productRegisterHandler
     */
    public function __construct(
        ProductRepositoryInterface $productRepository,
        ProductRegisterHandlerInterface $productRegisterHandler
    )
    {
        parent::__construct();

        $this->productRepository = $productRepository;
        $this->productRegisterHandler = $productRegisterHandler;
    }

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('odiseo:mailchimp:sync-products')
            ->setDescription('Synchronize the products to Mailchimp.')
        ;
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Synchronizing the products to Mailchimp.');

        $this->registerProducts();
    }

    protected function registerProducts()
    {
        $products = $this->productRepository->findAll();

        $this->io->text('Connecting '.count($products).' products.');
        $this->io->progressStart(count($products));

        /** @var ProductInterface $product */
        foreach ($products as $product) {
            $channels = $product->getChannels();

            foreach($channels as $channel) {
                try {
                    $response = $this->productRegisterHandler->register($product, $channel);

                    if (!isset($response['id']) && $response !== false) {
                        $this->io->error('Status: '.$response['status'].', Detail: '.$response['detail']);

                        if (is_array($response['errors']) && count($response['errors']) > 0) {
                            $this->io->listing($response['errors']);
                        }
                    }

                    $this->io->progressAdvance(1);
                } catch(\Exception $e) {
                    $this->io->error($e->getMessage());
                }
            }
        }

        $this->io->progressFinish();
        $this->io->success('The products has been synchronized successfully.');
    }
}