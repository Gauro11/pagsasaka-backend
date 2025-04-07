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
        $this->middleware('auth:sanctum')->only(['store', 'respond']);
    }

    // Store a new rating with an optional comment
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500',
        ]);

        $account = Auth::user();

        if (!$account) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $existingRating = Rating::where('account_id', $account->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existingRating) {
            return response()->json(['error' => 'You have already rated this product.'], 403);
        }

        $rating = Rating::create([
            'account_id' => $account->id,
            'product_id' => $product->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        $averageRating = $product->averageRating();
        $totalRatings = $product->totalRatings();

        return response()->json([
            'success' => 'Rating submitted successfully!',
            'average_rating' => number_format($averageRating, 1),
            'total_ratings' => $totalRatings,
            'rating' => [
                'stars' => $rating->rating,
                'comment' => $rating->comment,
            ],
        ]);
    }

    // Get the ratings for a product, including comments and seller responses
    public function index(Product $product)
    {
        $averageRating = $product->averageRating();
        $totalRatings = $product->totalRatings();

        // Fetch all ratings with comments and seller responses for the product
        $ratings = Rating::where('product_id', $product->id)
            ->with('account:id,first_name,last_name')
            ->select('id', 'rating', 'comment', 'seller_response', 'created_at', 'account_id')
            ->paginate(10); // Add pagination for scalability

        return response()->json([
            'average_rating' => number_format($averageRating, 1),
            'total_ratings' => $totalRatings,
            'ratings' => $ratings,
        ]);
    }

    // Allow the seller to respond to a rating
    public function respond(Request $request, Rating $rating)
    {
        $request->validate([
            'seller_response' => 'required|string|max:1000',
        ]);

        $account = Auth::user();

        if (!$account) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Check if the authenticated user is the seller of the product or an admin
        $product = $rating->product;
        if ($account->id !== $product->account_id && $account->role->name !== 'admin') {
            return response()->json(['error' => 'You are not authorized to respond to this rating.'], 403);
        }

        // Update the rating with the seller's response
        $rating->update([
            'seller_response' => $request->seller_response,
        ]);

        return response()->json([
            'success' => 'Seller response added successfully!',
            'rating' => [
                'id' => $rating->id,
                'seller_response' => $rating->seller_response,
            ],
        ]);
    }
}