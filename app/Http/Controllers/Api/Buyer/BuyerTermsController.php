<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Terms;
use Exception;
use Illuminate\Http\Request;

class BuyerTermsController extends Controller
{
    /**
     * Get buyer policies (privacy policy, terms and condition, return policy)
     */
    public function index(Request $request)
    {
        try {
            $terms = Terms::first();
            
            if (!$terms) {
                return ResponseHelper::success([
                    'buyer_privacy_policy' => null,
                    'buyer_terms_and_condition' => null,
                    'buyer_return_policy' => null,
                ]);
            }

            return ResponseHelper::success([
                'buyer_privacy_policy' => $terms->buyer_privacy_policy,
                'buyer_terms_and_condition' => $terms->buyer_terms_and_condition,
                'buyer_return_policy' => $terms->buyer_return_policy,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get specific buyer policy by type
     */
    public function show(Request $request, $type)
    {
        try {
            $terms = Terms::first();
            
            if (!$terms) {
                return ResponseHelper::error('Policy not found', 404);
            }

            $allowedTypes = [
                'privacy-policy' => 'buyer_privacy_policy',
                'terms-and-condition' => 'buyer_terms_and_condition',
                'return-policy' => 'buyer_return_policy',
            ];

            if (!isset($allowedTypes[$type])) {
                return ResponseHelper::error('Invalid policy type', 422);
            }

            $field = $allowedTypes[$type];
            $policy = $terms->$field;

            if (!$policy) {
                return ResponseHelper::error('Policy not found', 404);
            }

            return ResponseHelper::success([
                'type' => $type,
                'content' => $policy,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}

