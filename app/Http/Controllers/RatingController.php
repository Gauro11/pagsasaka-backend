<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->only(['store']);
    }

    // Store a new rating with an optional comment
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500', // Add validation for comment
        ]);

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

        // Create the rating with comment
        $rating = Rating::create([
            'account_id' => $account->id,
            'product_id' => $product->id,
            'rating' => $request->rating,
            'comment' => $request->comment, // Include the comment
        ]);

        // Recalculate the average rating and total ratings
        $averageRating = $product->averageRating();
        $totalRatings = $product->totalRatings();

        return response()->json([
            'success' => 'Rating submitted successfully!',
            'average_rating' => number_format($averageRating, 1),
            'total_ratings' => $totalRatings,
            'rating' => [
                'stars' => $rating->rating,
                'comment' => $rating->comment, // Return the submitted comment
            ],
        ]);
    }

    // Get the ratings for a product, including comments
    public function index(Product $product)
{
    $averageRating = $product->averageRating();
    $totalRatings = $product->totalRatings();

    // Fetch all ratings with comments for the product
    $ratings = Rating::where('product_id', $product->id)
        ->with('account:id,first_name,last_name') // Ensure account details are loaded
        ->select('rating', 'comment', 'created_at', 'account_id') // Include account_id in the selection
        ->get();

    return response()->json([
        'average_rating' => number_format($averageRating, 1),
        'total_ratings' => $totalRatings,
        'ratings' => $ratings, // Include individual ratings with comments
    ]);
}


}