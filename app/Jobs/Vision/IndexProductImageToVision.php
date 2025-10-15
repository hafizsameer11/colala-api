<?php

namespace App\Jobs\Vision;

use App\Models\ProductImage;
use App\Models\Product;
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Vision\V1\Client\ProductSearchClient;
use Google\Cloud\Vision\V1\ReferenceImage;
use Google\Cloud\Vision\V1\CreateReferenceImageRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class IndexProductImageToVision implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [10, 30, 60, 120, 300];

    public function __construct(public int $productImageId) {}

    public function handle(): void
    {
        $image = ProductImage::find($this->productImageId);
        if (!$image) {
            return;
        }

        $product = Product::find($image->product_id);
        if (!$product) {
            return;
        }

        // If product is not yet indexed, queue it and wait
        if (!$product->vision_product_name) {
            dispatch(new IndexProductToVision($product->id))->onQueue('vision');
            $this->release(10);
            return;
        }

        $projectId = config('services.google.project_id');
        $location = config('services.google.location', 'us-west1');
        $bucket = config('services.google.bucket');
        $storage = null;
        $client = null;

        try {
            // Upload to GCS
            $credentialsPath = storage_path('app/google/service-account.json');
            if (!file_exists($credentialsPath)) {
                $credentialsPath = base_path($credentialsPath);
            }
            
            $storage = new StorageClient([
                'keyFilePath' => $credentialsPath,
            ]);
            $gcsBucket = $storage->bucket($bucket);

            $localPath = storage_path('app/public/' . $image->path);
            $objectPath = "products/{$product->id}/" . Str::uuid() . '.' . pathinfo($localPath, PATHINFO_EXTENSION);
            $gcsBucket->upload(fopen($localPath, 'r'), ['name' => $objectPath]);

            $gcsUri = sprintf('gs://%s/%s', $bucket, $objectPath);

            // Create reference image
            $client = new ProductSearchClient([
                'credentials' => $credentialsPath,
                'apiEndpoint' => sprintf('%s-vision.googleapis.com', $location)
            ]);

            $refId = 'ref-' . $image->id . '-' . Str::random(6);
            $refImage = new ReferenceImage();
            $refImage->setUri($gcsUri);

            $request = new CreateReferenceImageRequest();
            $request->setParent($product->vision_product_name);
            $request->setReferenceImage($refImage);
            $request->setReferenceImageId($refId);
            
            $createdRef = $client->createReferenceImage($request);

            // Update ProductImage
            $image->update([
                'gcs_uri' => $gcsUri,
                'vision_reference_image_name' => $createdRef->getName(),
                'vision_index_status' => 'indexed',
                'vision_last_error' => null,
            ]);

            // Update Product if needed
            if ($product->vision_index_status !== 'indexed') {
                $product->update([
                    'vision_index_status' => 'indexed',
                    'vision_indexed_at' => now(),
                    'vision_last_error' => null,
                ]);
            }

        } catch (\Throwable $e) {
            $image->update([
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
        ProductImage::whereKey($this->productImageId)->update([
            'vision_index_status' => 'failed',
            'vision_last_error' => $e->getMessage(),
        ]);
    }
}
