<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\Terms;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminTermsController extends Controller
{
    /**
     * Get all terms and policies
     */
    public function index()
    {
        try {
            $terms = Terms::first();
            
            // If no terms exist, create a default record
            if (!$terms) {
                $terms = Terms::create([]);
            }

            return ResponseHelper::success([
                'terms' => $this->formatTerms($terms)
            ]);
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Update terms and policies
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'buyer_privacy_policy' => 'nullable|string',
                'buyer_terms_and_condition' => 'nullable|string',
                'buyer_return_policy' => 'nullable|string',
                'seller_onboarding_policy' => 'nullable|string',
                'seller_privacy_policy' => 'nullable|string',
                'seller_terms_and_condition' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseHelper::error($validator->errors()->first(), 422);
            }

            $terms = Terms::first();
            
            // If no terms exist, create a new record
            if (!$terms) {
                $terms = Terms::create($request->only([
                    'buyer_privacy_policy',
                    'buyer_terms_and_condition',
                    'buyer_return_policy',
                    'seller_onboarding_policy',
                    'seller_privacy_policy',
                    'seller_terms_and_condition',
                ]));
            } else {
                // Update existing record
                $terms->update($request->only([
                    'buyer_privacy_policy',
                    'buyer_terms_and_condition',
                    'buyer_return_policy',
                    'seller_onboarding_policy',
                    'seller_privacy_policy',
                    'seller_terms_and_condition',
                ]));
            }

            return ResponseHelper::success([
                'terms' => $this->formatTerms($terms->fresh())
            ], 'Terms and policies updated successfully');
        } catch (Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Format terms for response
     */
    private function formatTerms(Terms $terms): array
    {
        return [
            'id' => $terms->id,
            'buyer_privacy_policy' => $terms->buyer_privacy_policy,
            'buyer_terms_and_condition' => $terms->buyer_terms_and_condition,
            'buyer_return_policy' => $terms->buyer_return_policy,
            'seller_onboarding_policy' => $terms->seller_onboarding_policy,
            'seller_privacy_policy' => $terms->seller_privacy_policy,
            'seller_terms_and_condition' => $terms->seller_terms_and_condition,
            'created_at' => $terms->created_at,
            'updated_at' => $terms->updated_at,
        ];
    }
}

