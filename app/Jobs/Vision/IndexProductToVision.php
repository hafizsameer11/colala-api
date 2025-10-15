<?php

namespace App\Jobs\Vision;

use App\Models\Product;
use Google\Cloud\Vision\V1\Client\ProductSearchClient;
use Google\Cloud\Vision\V1\Product as VisionProduct;
use Google\Cloud\Vision\V1\Product\KeyValue;
use Google\Cloud\Vision\V1\ProductSet;
use Google\Cloud\Vision\V1\CreateProductSetRequest;
use Google\Cloud\Vision\V1\CreateProductRequest;
use Google\Cloud\Vision\V1\AddProductToProductSetRequest;
use Google\Cloud\Vision\V1\GetProductSetRequest;
use Google\Cloud\Vision\V1\GetProductRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IndexProductToVision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 300];

    public function __construct(public int $productId) {}

    public function handle(): void
    {
        $product = Product::find($this->productId);
        if (!$product) {
            return;
        }

        $projectId = config('services.google.project_id');
        $location = config('services.google.location', 'us-west1');
        $client = null;

        try {
            $client = new ProductSearchClient([
                'credentials' => base_path(env('GOOGLE_APPLICATION_CREDENTIALS')),
                'apiEndpoint' => sprintf('%s-vision.googleapis.com', $location)
            ]);

            $parent = sprintf('projects/%s/locations/%s', $projectId, $location);

            // Determine ProductSet
            $productSetId = $product->vision_product_set ?: 'fashion-set';
            $productSetName = $client->productSetName($projectId, $location, $productSetId);

            // Ensure ProductSet exists
            try {
                $request = new GetProductSetRequest();
                $request->setName($productSetName);
                $client->getProductSet($request);
            } catch (\Throwable) {
                $productSet = new ProductSet();
                $productSet->setDisplayName(ucfirst($productSetId));
                
                $request = new CreateProductSetRequest();
                $request->setParent($parent);
                $request->setProductSet($productSet);
                $request->setProductSetId($productSetId);
                
                $client->createProductSet($request);
            }

            // Ensure Product exists
            $visionProductId = "product-{$product->id}";
            $visionProductName = $client->productName($projectId, $location, $visionProductId);

            try {
                $request = new GetProductRequest();
                $request->setName($visionProductName);
                $client->getProduct($request);
            } catch (\Throwable) {
                $vp = new VisionProduct();
                $vp->setDisplayName($product->name);
                $vp->setProductCategory('apparel-v2');
                
                $labels = [];
                if ($product->brand) {
                    $labels[] = new KeyValue(['key' => 'brand', 'value' => (string)$product->brand]);
                }
                $labels[] = new KeyValue(['key' => 'local_id', 'value' => (string)$product->id]);
                $vp->setProductLabels($labels);

                $request = new CreateProductRequest();
                $request->setParent($parent);
                $request->setProduct($vp);
                $request->setProductId($visionProductId);
                
                $created = $client->createProduct($request);
                $visionProductName = $created->getName();
            }

            // Add product to product set (idempotent)
            try {
                $request = new AddProductToProductSetRequest();
                $request->setName($productSetName);
                $request->setProduct($visionProductName);
                $client->addProductToProductSet($request);
            } catch (\Throwable) {
                // Ignore already-added errors
            }

            // Update Product
            $product->update([
                'vision_product_name' => $visionProductName,
                'vision_product_set' => $productSetId,
                'vision_index_status' => 'indexed',
                'vision_indexed_at' => now(),
                'vision_last_error' => null,
            ]);

        } catch (\Throwable $e) {
            $product->update([
                'vision_index_status' => 'failed',
                'vision_last_error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($client) {
                $client->close();
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Product::whereKey($this->productId)->update([
            'vision_index_status' => 'failed',
            'vision_last_error' => $e->getMessage(),
        ]);
    }
}
