<?php

namespace App\Http\Controllers\Api\Seller;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Terms;
use Exception;
use Illuminate\Http\Request;

class SellerTermsController extends Controller
{
    /**
     * Get seller policies (onboarding policy, privacy policy, terms and condition)
     */
    public function index(Request $request)
    {
        try {
            $terms = Terms::first();
            
            if (!$terms) {
                return ResponseHelper::success([
                    'seller_onboarding_policy' => null,
                    'seller_privacy_policy' => null,
                    'seller_terms_and_condition' => null,
                ]);
            }

            return ResponseHelper::success([
                'seller_onboarding_policy' => $terms->seller_onboarding_policy,
                'seller_privacy_policy' => $terms->seller_privacy_policy,
                'seller_terms_and_condition' => $terms->seller_terms_and_condition,
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Get specific seller policy by type
     */
    public function show(Request $request, $type)
    {
        try {
            $terms = Terms::first();
            
            if (!$terms) {
                return ResponseHelper::error('Policy not found', 404);
            }

            $allowedTypes = [
                'onboarding-policy' => 'seller_onboarding_policy',
                'privacy-policy' => 'seller_privacy_policy',
                'terms-and-condition' => 'seller_terms_and_condition',
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

