<?php

namespace App\Services;

use App\Models\SavedItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavedItemService
{
    /**
     * Toggle save/unsave for product/service/post.
     * $type must be one of: product, service, post
     */
    public function toggle(int $userId, string $type, int $typeId): array
    {
        $column = $this->resolveColumn($type);

        $saved = SavedItem::where('user_id', $userId)
            ->where($column, $typeId)
            ->first();

        if ($saved) {
            $saved->delete();
            return ['saved' => false];
        }

        $new = SavedItem::create([
            'user_id'   => $userId,
            $column     => $typeId,
        ]);

        return ['saved' => true, 'id' => $new->id];
    }

    /**
     * List all saved items for a user grouped by type.
     */
    public function list(int $userId)
    {
        return SavedItem::with(['product.images', 'service', 'post'])
            ->where('user_id', $userId)
            ->latest()
            ->get()
            ->map(function ($saved) {
                $base = [
                    'id'        => $saved->id,
                    'saved_at'  => $saved->created_at,
                    'type'      => $saved->type, // accessor below
                ];

                if ($saved->product) {
                    return array_merge($base, [
                        'product_id'     => $saved->product_id,
                        'name'           => $saved->product->name,
                        'price'          => $saved->product->price,
                        'discount_price' => $saved->product->discount_price,
                        'images'         => $saved->product->images->map(fn ($img) => asset('storage/' . $img->path)),
                    ]);
                }

                if ($saved->service) {
                    return array_merge($base, [
                        'service_id' => $saved->service_id,
                        'name'       => $saved->service->name,
                        'price'      => $saved->service->price_to. '-'. $saved->service->price_from,
                        'media'      => $saved->service->media->map(fn ($img) => asset('storage/' . $img->path)),
                    ]);
                }

                if ($saved->post) {
                    return array_merge($base, [
                       'post'=>$saved->post
                        
                    ]);
                }

                return $base;
            });
    }

    /**
     * Check if given type/id is already saved.
     */
    public function isSaved(int $userId, string $type, int $typeId): bool
    {
        $column = $this->resolveColumn($type);

        return SavedItem::where('user_id', $userId)
            ->where($column, $typeId)
            ->exists();
    }

    /**
     * Ensure type is valid and return its DB column.
     */
    protected function resolveColumn(string $type): string
    {
        return match ($type) {
            'product' => 'product_id',
            'service' => 'service_id',
            'post'    => 'post_id',
            default   => throw ValidationException::withMessages([
                'type' => 'Invalid type; must be product, service or post.',
            ]),
        };
    }
}
