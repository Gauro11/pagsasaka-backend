<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    // Apply Sanctum middleware to protect the rating submission
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store']);
    }

    // Store a new rating
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'rating' => 'required|integer|between:1,5',
        ]);

        // Use Sanctum's authenticated user
        $account = Auth::user();

        if (!$account) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Check if the account has already rated this product
        $existingRating = Rating::where('account_id', $account->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingRating) {
            return response()->json(['error' => 'You have already rated this product.'], 403);
        }

        // Create the rating
        $rating = Rating::create([
            'account_id' => $account->id,
            'product_id' => $product->id,
            'rating' => $request->rating,
        ]);

        // Recalculate the average rating and total ratings
        $averageRating = $product->averageRating();
        $totalRatings = $product->totalRatings();

        return response()->json([
            'success' => 'Rating submitted successfully!',
            'average_rating' => number_format($averageRating, 1),
            'total_ratings' => $totalRatings,
        ]);
    }

    // Get the ratings for a product
    public function index(Product $product)
    {
        $averageRating = $product->averageRating();
        $totalRatings = $product->totalRatings();

        return response()->json([
            'average_rating' => number_format($averageRating, 1),
            'total_ratings' => $totalRatings,
        ]);
    }
}
