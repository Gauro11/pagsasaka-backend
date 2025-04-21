<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\ApiLog;
use App\Models\Product;
use App\Models\Account;
use App\Models\Order;
use App\Models\Cart;
use App\Models\BuyNow;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function addProduct(Request $request)
    {
        try {
            // Validate request
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'product_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'stocks' => 'required|integer|min:0',
                'unit' => 'required|string|in:kg,net,box',
                'product_img' => 'required|array|min:3',
                'product_img.*' => 'max:2048',
                'visibility' => 'required|in:Published,Scheduled',
            ]);

            // Ensure the user is authenticated
            if (!auth()->check()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in to add a product.',
                ];

                // Log the API call
                $this->logAPICalls('addProduct', null, $request->all(), $response);

                return response()->json($response, 401);
            }

            // Get the authenticated user's account ID
            $accountId = auth()->id();

            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('product_img')) {
                foreach ($request->file('product_img') as $image) {
                    $directory = public_path('img/products');
                    $fileName = 'Product-' . $accountId . '-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $image->getClientOriginalExtension();

                    if (!file_exists($directory)) {
                        mkdir($directory, 0755, true);
                    }

                    $image->move($directory, $fileName);
                    $imagePaths[] = asset('img/products/' . $fileName);
                }
            }

            // Save product
            $validated['product_img'] = $imagePaths; // Save as array
            $validated['account_id'] = $accountId;

            $product = Product::create($validated);

            $response = [
                'isSuccess' => true,
                'message' => 'Product successfully created.',
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => number_format($product->price, 2), // Format price with commas
                    'stocks' => $product->stocks,
                    'unit' => $product->unit,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'visibility' => $product->visibility,
                ],
            ];

            // Log the API call
            $this->logAPICalls('addProduct', $product->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the product.',
                'error' => $e->getMessage(),
            ];

            // Log the API call
            $this->logAPICalls('addProduct', null, $request->all(), $response);

            return response()->json($response, 500);
        }
    }


    public function editProduct(Request $request, $id)
    {
        try {
            // Find the product by ID
            $product = Product::findOrFail($id);

            // Validate the request (fields are optional)
            $validated = $request->validate([
                'category_id' => 'sometimes|exists:categories,id',
                'product_name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'price' => 'sometimes|numeric|min:0',
                'stocks' => 'sometimes|integer|min:0',
                'unit' => 'sometimes|string|in:kg,net,box',
                'product_img' => 'sometimes|array',
                'product_img.*' => 'sometimes|max:2048',
                'visibility' => 'sometimes|in:Published,Scheduled',
            ]);

            // Handle image uploads if provided
            if ($request->hasFile('product_img')) {
                $directory = public_path('img/products');
                $imagePaths = [];

                // Ensure the directory exists
                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                // Delete old images
                if (!empty($product->product_img)) {
                    foreach ($product->product_img as $oldImage) {
                        $path = str_replace(asset(''), '', $oldImage);
                        $fullPath = public_path($path);

                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                }

                // Upload new images
                foreach ($request->file('product_img') as $file) {
                    $fileName = 'Product-' . $product->id . '-' . now()->format('YmdHis') . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->move($directory, $fileName);
                    $imagePaths[] = asset('img/products/' . $fileName);
                }

                // Update the validated data with the new image paths
                $validated['product_img'] = $imagePaths;
            }

            // Update the product with the validated data
            $product->update($validated);

            $response = [
                'isSuccess' => true,
                'message' => 'Product successfully created.',
                'product' => [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => number_format($product->price, 2),
                    'stocks' => $product->stocks,
                    'unit' => $product->unit,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'visibility' => $product->visibility,
                ],
            ];

            // Log API call
            $this->logAPICalls('editProduct', $product->id, $request->all(), $response);

            return response()->json($response, 200);
        } catch (ModelNotFoundException $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Product not found.',
            ];

            return response()->json($response, 404);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];

            return response()->json($response, 422);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the product.',
                'error' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    public function getAllProductsList(Request $request)
    {
        try {
            // Get optional search query
            $searchTerm = $request->input('search', null);
            $perPage = 6;

            // Query for fetching products
            $query = Product::select('id', 'product_name', 'description', 'price', 'stocks', 'product_img', 'unit', 'category_id', 'visibility', 'is_archived')
                ->where('is_archived', '0')
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('product_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                });

            // Paginate results
            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No products found matching the criteria.',
                ], 404);
            }

            // ✅ Corrected the image formatting issue
            $formattedProducts = $result->getCollection()->map(function ($product) {

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => number_format($product->price, 2),
                    'stocks' => $product->stocks,
                    'unit' => $product->unit,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'visibility' => $product->visibility,
                    'is_archived' => $product->is_archived == 0,
                ];
            });

            // Return response
            return response()->json([
                'isSuccess' => true,
                'message' => 'Products retrieved successfully.',
                'products' => $formattedProducts,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllProductbyId(Request $request)
    {
        try {
            // Get optional search query
            $searchTerm = $request->input('search', null);
            $perPage = 10;

            // Query for fetching products
            $query = Product::select('id', 'product_name', 'description', 'price', 'stocks', 'unit', 'product_img', 'category_id', 'visibility', 'is_archived')
                ->where('is_archived', '0')
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('product_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                });

            // Paginate results
            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No products found matching the criteria.',
                ], 404);
            }

            // ✅ Corrected the image formatting issue
            $formattedProducts = $result->getCollection()->map(function ($product) {

                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => number_format($product->price, 2),
                    'stocks' => $product->stocks,
                    'unit' => $product->unit,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'visibility' => $product->visibility,
                    'is_archived' => $product->is_archived == 0,
                ];
            });

            // Return response
            return response()->json([
                'isSuccess' => true,
                'message' => 'Products retrieved successfully.',
                'products' => $formattedProducts,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProductById($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            // Fetch the seller account using account_id
            $sellerAccount = Account::find($product->account_id);

            $sellerName = null;
            $sellerAvatar = null;

            if ($sellerAccount) {
                $sellerName = trim("{$sellerAccount->first_name} {$sellerAccount->middle_name} {$sellerAccount->last_name}");
                $sellerAvatar = $sellerAccount->avatar;
            }

            // Count only non-archived products for this seller
            $totalProducts = Product::where('account_id', $product->account_id)
                ->where('is_archived', 0)
                ->count();

            $responseProduct = [
                'id' => $product->id,
                'category_id' => $product->category_id,
                'product_name' => $product->product_name,
                'description' => $product->description,
                'price' => number_format($product->price, 2),
                'stocks' => $product->stocks,
                'unit' => $product->unit,
                'product_img' => $product->product_img,
                'visibility' => $product->visibility,
                'account_id' => $product->account_id,
                'seller_name' => $sellerName,
                'seller_avatar' => $sellerAvatar,
                'total_products' => $totalProducts,
                'is_archived' => $product->is_archived,
                'created_at' => $product->created_at,
                'updated_at' => $product->updated_at,
            ];

            return response()->json([
                'isSuccess' => true,
                'message' => 'Product retrieved successfully.',
                'product' => [$responseProduct],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve the product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProductsByAccountId(Request $request)
    {
        try {
            // Ensure the user is authenticated
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in to view products.',
                ], 401);
            }

            $accountId = $user->id;

            // Optional query parameters
            $searchTerm = $request->input('search');
            $perPage = $request->input('per_page', 10);

            // Build the query
            $query = Product::select(
                'id',
                'product_name',
                'description',
                'price',
                'stocks',
                'unit',
                'product_img',
                'category_id',
                'visibility',
                'is_archived'
            )
                ->where('account_id', $accountId)
                ->where('is_archived', '0')
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($subQuery) use ($searchTerm) {
                        $subQuery->where('product_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                });

            // Paginate the results
            $products = $query->paginate($perPage);

            if ($products->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No products found for your account matching the criteria.',
                ], 404);
            }

            // Prepare seller info
            $fullName = trim("{$user->first_name} {$user->middle_name} {$user->last_name}");
            $avatar = $user->avatar ?? null; // Get avatar if available
            $totalProducts = Product::where('account_id', $accountId)
                ->where('is_archived', '0')
                ->count();

            // Format the products
            $formattedProducts = $products->getCollection()->transform(function ($product) use ($fullName, $totalProducts, $avatar) {
                return [
                    'id' => $product->id,
                    'avatar' => $avatar,
                    'name' => $fullName,
                    'total_products' => $totalProducts,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => number_format($product->price, 2),
                    'stocks' => $product->stocks,
                    'unit' => $product->unit,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'visibility' => $product->visibility,
                    'is_archived' => $product->is_archived == 0,
                ];
            });

            // Final response
            return response()->json([
                'isSuccess' => true,
                'message' => 'Products retrieved successfully.',
                'products' => $formattedProducts,
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function viewShop($accountId, Request $request)
    {
        try {
            $seller = Account::select('id', 'first_name', 'middle_name', 'last_name', 'avatar')
                ->where('id', $accountId)
                ->first();

            if (!$seller) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Seller not found.',
                ], 404);
            }

            $fullName = trim("{$seller->first_name} {$seller->middle_name} {$seller->last_name}");
            $avatar = $seller->avatar;

            $totalProducts = Product::where('account_id', $accountId)
                ->where('is_archived', '0')
                ->count();

            $perPage = $request->input('per_page', 10);
            $products = Product::select('id', 'product_name', 'description', 'price', 'stocks', 'unit', 'product_img', 'category_id', 'visibility', 'is_archived')
                ->where('account_id', $accountId)
                ->where('is_archived', '0')
                ->paginate($perPage);

            $formattedProducts = $products->getCollection()->transform(function ($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => number_format($product->price, 2),
                    'stocks' => $product->stocks,
                    'unit' => $product->unit,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'visibility' => $product->visibility,
                    'is_archived' => $product->is_archived == 0,
                ];
            });

            return response()->json([
                'isSuccess' => true,
                'message' => 'Shop products retrieved successfully.',
                'seller' => [
                    'name' => $fullName,
                    'avatar' => $avatar,
                    'total_products' => $totalProducts,
                ],
                'products' => $formattedProducts,
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to fetch shop products.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteProduct($id)
    {
        try {
            $product = Product::findOrFail($id); // Find or throw 404

            // Check if the product is already archived
            if ($product->is_archived == "1") {
                $response = [
                    'isSuccess' => false,
                    'message' => "Product has already been archived.",
                ];
                $this->logAPICalls('deleteProduct', $id, [], [$response]);
                return response()->json($response, 400); // Return a 400 Bad Request response
            }

            // Archive the product
            $product->update(['is_archived' => "1"]);

            $response = [
                'isSuccess' => true,
                'message' => "Product successfully deleted."
            ];
            $this->logAPICalls('deleteProduct', $id, [], [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the product.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteProduct', "", [], [$response]);
            return response()->json($response, 500);
        }
    }


    //seller dashboard
    public function getFarmerProductCount(Request $request)
    {
        try {
            // Get the authenticated user
            $farmer = Auth::user();
            Log::info('Authenticated User ID: ' . ($farmer ? $farmer->id : 'null'));

            if (!$farmer) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // Check if the user is a farmer using the role relationship
            $role = $farmer->role;
            if (!$role || $role->role !== 'Farmer') { // Use the 'role' column from the Role model
                Log::info('User role: ' . ($role ? $role->role : 'not found'));
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'User is not a farmer',
                    'totalProducts' => 0,
                ], 403);
            }

            // Get the total count of the farmer's products
            $totalProducts = Product::where('account_id', $farmer->id)
                ->where('is_archived', '0')
                ->count();
            Log::info('Total products for farmer ID ' . $farmer->id . ': ' . $totalProducts);

            // Return the total count
            return response()->json([
                'isSuccess' => true,
                'message' => 'Product count retrieved successfully.',
                'totalProducts' => $totalProducts,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error fetching product count: ' . $e->getMessage());
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve product count.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function buyProduct(Request $request, $product_id)
    // {
    //     $user = Auth::user();

    //     if (!$user) {
    //         $response = [
    //             'isSuccess' => false,
    //             'message' => 'User not authenticated',
    //         ];
    //         $this->logAPICalls('buyProduct', "", $request->all(), [$response]); // Log the failed API call
    //         return response()->json($response, 500);
    //     }

    //     try {
    //         $validated = $request->validate([
    //             'quantity' => 'required|integer|min:1',
    //         ]);

    //         $product = Product::find($product_id);

    //         if (!$product) {
    //             $response = [
    //                 'isSuccess' => false,
    //                 'message' => 'Product not found',
    //             ];
    //             $this->logAPICalls('buyProduct', "", $request->all(), [$response]); // Log the failed API call
    //             return response()->json($response, 500);
    //         }

    //         if ($product->stocks < $validated['quantity']) {
    //             $response = [
    //                 'isSuccess' => false,
    //                 'message' => 'Insufficient stock',
    //             ];
    //             $this->logAPICalls('buyProduct', $product->id, $request->all(), [$response]); // Log the failed API call
    //             return response()->json($response, 500);
    //         }

    //         // Deduct stock
    //         $product->stocks -= $validated['quantity'];
    //         $product->save();

    //         // Calculate total price
    //         $totalAmount = $product->price * $validated['quantity'];

    //         // Create order with 'processing' as the initial status
    //         $order = Order::create([
    //             'account_id' => $user->id,
    //             'product_id' => $product->id,
    //             'quantity' => $validated['quantity'],
    //             'total_amount' => $totalAmount,
    //             'status' => 'processing', // Default status set to 'processing'
    //             'ship_to' => $request->input('ship_to', 'Default Shipping Address'), // Provide a default if not sent
    //             'created_at' => now()->format('Y-m-d H:i:s'), // Explicitly set created_at
    //             'updated_at' => now()->format('Y-m-d H:i:s'), // Explicitly set updated_at
    //         ]);

    //         $response = [
    //             'isSuccess' => true,
    //             'message' => 'Order placed successfully',
    //             'order' => [
    //                 'id' => $order->id,
    //                 'account_id' => $order->account_id,
    //                 'product_id' => $order->product_id,
    //                 'quantity' => $order->quantity,
    //                 'total_amount' => $order->total_amount,
    //                 'status' => $order->status, // Include the status in the response
    //                 'created_at' => Carbon::parse($order->created_at)->format('F d Y'),
    //                 'updated_at' => Carbon::parse($order->updated_at)->format('F d Y'),
    //             ],
    //         ];
    //         $this->logAPICalls('buyProduct', $product->id, $request->all(), [$response]); // Log the successful API call
    //         return response()->json($response, 200);
    //     } catch (Throwable $e) {
    //         $response = [
    //             'isSuccess' => false,
    //             'message' => 'An error occurred while placing the order.',
    //             'error' => $e->getMessage(),
    //         ];
    //         $this->logAPICalls('buyProduct', "", $request->all(), [$response]); // Log the exception
    //         return response()->json($response, 500);
    //     }
    // }

    public function addToCart(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            $product = Product::find($id);

            if (!$product) {
                return response()->json(['isSuccess' => false, 'message' => 'Product not found'], 404);
            }

            $cart = Cart::where('account_id', $user->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cart) {
                // Update quantity and recalculate total
                $newQuantity = $cart->quantity + $validated['quantity'];

                if ($newQuantity > $product->stocks) {
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'Updated quantity exceeds available stock.',
                    ], 400);
                }

                $cart->quantity = $newQuantity;
                $cart->item_total = $newQuantity * $product->price;
                $cart->save();
            } else {
                $cart = Cart::create([
                    'account_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $validated['quantity'],
                    'unit' => $product->unit ?? 'default_unit',
                    'price' => $product->price, // Store price without formatting
                    'item_total' => $validated['quantity'] * $product->price, // Store total without formatting
                ]);
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Product updated in cart successfully',
                'cart' => [
                    'id' => $cart->id,
                    'account_id' => $cart->account_id,
                    'product_id' => $cart->product_id,
                    'product_img' => $product->product_img,
                    'quantity' => $cart->quantity,
                    'unit' => $cart->unit,
                    'price' => number_format($cart->price, 2), // Format with commas
                    'item_total' => number_format($cart->item_total, 2), // Format with commas
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while updating the cart.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function updateCartQuantity(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = Cart::where('id', $id)
            ->where('account_id', $user->id)
            ->first();

        if (!$cart) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $product = Product::find($cart->product_id);

        if ($validated['quantity'] > $product->stocks) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Requested quantity exceeds available stock',
            ], 400);
        }

        $cart->quantity = $validated['quantity'];
        $cart->item_total = $validated['quantity'] * $product->price;
        $cart->save();

        return response()->json([
            'success' => true,
            'message' => 'Cart quantity updated successfully',
            'data' => [
                'cart_id' => $cart->id,
                'account_id' => $cart->account_id,
                'product_id' => $cart->product_id,
                'quantity' => $cart->quantity,
                'unit' => $cart->unit,
                'price_per_unit' => $cart->price,
                'item_total' => $cart->item_total,
            ]
        ]);
    }

    public function getCartList()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $cartItems = Cart::where('account_id', $user->id)->get();

            $totalAmount = 0;
            $cartData = [];

            foreach ($cartItems as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $totalAmount += $item->item_total;

                    $cartData[] = [
                        'id' => $item->id,
                        'product_name' => $product->product_name,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'unit' => $item->unit,
                        'price' => number_format($item->price, 2), // Format price
                        'item_total' => number_format($item->item_total, 2), // Format item total
                        'product_img' => $product->product_img,
                    ];
                }
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Cart items retrieved successfully.',
                'cart' => $cartData,
                'totalAmount' => number_format($totalAmount, 2), // Format total amount
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while retrieving the cart items.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteFromCart($cartId)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "User not authenticated.",
                ], 401);
            }

            // Find the cart item by its ID and ensure it belongs to the authenticated user
            $cartItem = Cart::where('id', $cartId)
                ->where('account_id', $user->id)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => "Cart item not found.",
                ], 404);
            }

            // Delete the cart item
            $cartItem->delete();

            return response()->json([
                'isSuccess' => true,
                'message' => "Product successfully removed from cart.",
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => "Failed to remove product from cart.",
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // public function buyNow(Request $request, $id)
    // {
    //     $user = Auth::user();

    //     if (!$user) {
    //         return response()->json([
    //             'isSuccess' => false,
    //             'message' => 'User not authenticated.',
    //         ], 401);
    //     }

    //     try {
    //         $product = Product::find($id);
    //         if (!$product) {
    //             return response()->json([
    //                 'isSuccess' => false,
    //                 'message' => 'Product not found.',
    //             ], 404);
    //         }

    //         // Get quantity from cache or default to 1
    //         $cacheKey = 'purchase_' . $user->id . '_' . $product->id;
    //         $quantity = Cache::get($cacheKey, 1);

    //         // Clamp quantity between 1 and product stock
    //         $quantity = max(1, min($quantity, $product->stocks));

    //         // Calculate item total
    //         $itemTotal = $product->price * $quantity;

    //         // Update or create cart item
    //         $cartItem = Cart::updateOrCreate(
    //             [
    //                 'account_id' => $user->id,
    //                 'product_id' => $product->id,
    //             ],
    //             [
    //                 'price' => $product->price,
    //                 'quantity' => $quantity,
    //                 'item_total' => $itemTotal,
    //                 'unit' => $product->unit,
    //             ]
    //         );

    //         return response()->json([
    //             'isSuccess' => true,
    //             'message' => 'Product added to cart.',
    //             'cart_item' => [
    //                 'id' => $cartItem->id,
    //                 'product_id' => $product->id,
    //                 'product_name' => $product->product_name,
    //                 'quantity' => $quantity,
    //                 'unit' => $product->unit,
    //                 'price' => number_format($product->price, 2),
    //                 'item_total' => number_format($itemTotal, 2),
    //                 'product_img' => $product->product_img,
    //             ],
    //             'buyer_info' => [
    //                 'name' => $user->first_name . ' ' . $user->last_name,
    //                 'contact_number' => $user->phone_number,
    //                 'delivery_address' => $user->delivery_address,
    //             ],
    //         ]);
    //     } catch (Throwable $e) {
    //         return response()->json([
    //             'isSuccess' => false,
    //             'message' => 'An error occurred while processing buy now.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function buyNow(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            Log::warning('BuyNow attempted without authentication', [
                'ip' => $request->ip(),
                'product_id' => $id,
                'endpoint' => $request->fullUrl(),
            ]);
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);

            $product = Product::find($id);

            if (!$product) {
                Log::notice('Product not found in BuyNow', [
                    'product_id' => $id,
                    'user_id' => $user->id,
                    'endpoint' => $request->fullUrl(),
                ]);
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $cartItem = Cart::where('account_id', $user->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $validated['quantity'];

                if ($newQuantity > $product->stocks) {
                    Log::warning('Quantity exceeds stock in BuyNow', [
                        'user_id' => $user->id,
                        'product_id' => $product->id,
                        'requested_quantity' => $newQuantity,
                        'available_stocks' => $product->stocks,
                        'endpoint' => $request->fullUrl(),
                    ]);
                    return response()->json([
                        'isSuccess' => false,
                        'message' => 'Total quantity exceeds available stock.',
                    ], 400);
                }

                $cartItem->quantity = $newQuantity;
                $cartItem->item_total = $newQuantity * $product->price;
                $cartItem->status = 'InCart';
                $cartItem->save();
            } else {
                $cartItem = Cart::create([
                    'account_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $validated['quantity'],
                    'unit' => $product->unit ?? 'unit',
                    'price' => $product->price,
                    'item_total' => $validated['quantity'] * $product->price,
                    'status' => 'InCart',
                ]);
            }

            $product->decrement('stocks', $validated['quantity']);

            Log::info('Product added to cart via BuyNow', [
                'user_id' => $user->id,
                'cart_id' => $cartItem->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'endpoint' => $request->fullUrl(),
            ]);

            return response()->json([
                'isSuccess' => true,
                'message' => 'Product added to cart successfully.',
                'order_summary' => [
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'quantity' => $cartItem->quantity,
                    'unit' => $product->unit ?? 'unit',
                    'price' => number_format($product->price, 2),
                    'item_total' => number_format($cartItem->item_total, 2),
                    'product_img' => $product->product_img,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'phone_number' => $user->phone_number,
                    'address' => $user->delivery_address ?? 'N/A',
                    'cart_id' => $cartItem->id
                ]
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error in BuyNow', [
                'user_id' => $user->id ?? null,
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'endpoint' => $request->fullUrl(),
            ]);
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred during Buy Now',
                'order_summary' => [],
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }
    
    public function checkoutItem(Request $request, $id)
    {
        $account = Auth::user();
        $accountId = $account->account_id ?? $account->id;

        if (!$account) {
            Log::warning('CheckoutItem attempted without authentication', [
                'ip' => $request->ip(),
                'cart_id' => $id,
                'endpoint' => $request->fullUrl(),
            ]);
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        try {
            // Validate cart_id
            if (!is_numeric($id) || $id <= 0) {
                Log::warning('Invalid or missing cart_id in CheckoutItem', [
                    'user_id' => $accountId,
                    'cart_id' => $id,
                    'ip' => $request->ip(),
                    'endpoint' => $request->fullUrl(),
                ]);
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Invalid or missing cart ID. Expected a valid numeric cart ID.',
                ], 400);
            }

            $cartItem = Cart::where('id', $id)
                ->where('account_id', $accountId)
                ->where('status', 'InCart')
                ->first();

            if (!$cartItem) {
                Log::notice('Cart item not found or already checked out in CheckoutItem', [
                    'user_id' => $accountId,
                    'cart_id' => $id,
                    'endpoint' => $request->fullUrl(),
                ]);
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Cart item not found or already checked out.',
                ], 404);
            }

            $product = Product::find($cartItem->product_id);
            if (!$product) {
                Log::error('Product not found in CheckoutItem', [
                    'user_id' => $accountId,
                    'cart_id' => $id,
                    'product_id' => $cartItem->product_id,
                    'endpoint' => $request->fullUrl(),
                ]);
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Product not found.',
                ], 404);
            }

            $cartItem->status = 'CheckedOut';
            $cartItem->save();

            Log::info('Cart item checked out successfully', [
                'user_id' => $accountId,
                'cart_id' => $id,
                'product_id' => $product->id,
                'endpoint' => $request->fullUrl(),
            ]);

            $totalPrice = $cartItem->price * $cartItem->quantity;

            return response()->json([
                'isSuccess' => true,
                'message' => 'Item checked out successfully.',
                'checkout_details' => [
                    'cart_id' => $cartItem->id,
                    'product_name' => $product->product_name,
                    'quantity' => $cartItem->quantity,
                    'unit' => $cartItem->unit,
                    'price_per_unit' => number_format($cartItem->price, 2),
                    'item_total' => number_format($cartItem->item_total, 2),
                    'total_price' => number_format($totalPrice, 2),
                    'product_img' => $product->product_img,
                    'shipping_address' => $account->delivery_address ?? 'N/A',
                ],
            ], 200);
        } catch (Throwable $e) {
            Log::error('CheckoutItem error', [
                'user_id' => $accountId ?? null,
                'cart_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'endpoint' => $request->fullUrl(),
            ]);
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred during the checkout process.',
                'error' => app()->environment('production') ? null : $e->getMessage(),
            ], 500);
        }
    }

    public function getCheckoutPreview(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        $request->validate([
            'cart_id' => 'required|array|min:1',
            'cart_id.*' => 'integer',
        ]);

        $cartIds = $request->cart_ids;

        // Get cart items that belong to the authenticated user only
        $cartItems = Cart::where('account_id', $user->id)
            ->whereIn('id', $cartIds)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'No valid cart items found for this user.',
            ], 404);
        }

        $cartData = [];

        foreach ($cartItems as $item) {
            $product = Product::find($item->product_id);

            if (!$product) {
                continue; // skip if product was deleted
            }

            $cartData[] = [
                'id' => $item->id,
                'product_name' => $product->product_name,
                'product_id' => $product->id,
                'quantity' => $item->quantity,
                'unit' => $product->unit ?? 'unit',
                'price' => number_format($product->price, 2),
                'item_total' => number_format($item->quantity * $product->price, 2),
                'product_img' => $product->product_img,
            ];
        }

        return response()->json([
            'isSuccess' => true,
            'cart_info' => $cartData,
        ], 200);
    }

    public function getCartItemDetails(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            // Check the user's ID and the cart item ID
            Log::info('User ID: ' . $user->id);
            Log::info('Requested Cart Item ID: ' . $id);

            // Retrieve the specific cart item using the passed $id
            $cartItem = Cart::where('account_id', $user->id)
                ->where('id', $id)
                ->first();

            // Log if no cart item is found
            if (!$cartItem) {
                Log::warning('Cart item not found for User ID: ' . $user->id . ' with Cart ID: ' . $id);
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Cart item not found',
                ], 404);
            }

            // Retrieve the product details associated with the cart item
            $product = Product::find($cartItem->product_id);
            if (!$product) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            // Prepare the detailed cart item data
            $cartItemDetails = [
                'id' => $cartItem->id,
                'product_name' => $product->product_name,
                'product_id' => $product->id,
                'quantity' => $cartItem->quantity,
                'unit' => $product->unit ?? 'unit',
                'price' => number_format($cartItem->price, 2),  // Format price
                'item_total' => number_format($cartItem->item_total, 2),  // Format item total
                'product_img' => $product->product_img,
                'product_description' => $product->description ?? 'No description available',
                'available_stock' => $product->stocks,
                'category' => $product->category->name ?? 'N/A',  // Assuming category relation exists
                'shipping_address' => $user->delivery_address ?? 'N/A',
                'order_notes' => $cartItem->notes ?? 'No notes available',  // Add any notes if needed
            ];

            return response()->json([
                'isSuccess' => true,
                'message' => 'Cart item details retrieved successfully.',
                'cart_item_details' => $cartItemDetails,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while retrieving the cart item details.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getCartListStatus()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        try {
            $cartItems = Cart::where('account_id', $user->id)
                ->where('status', 'CheckedOut')
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No checked out items found.',
                    'cart_statuses' => [],
                ], 404);
            }

            $cartStatuses = [];

            // Combine user full name
            $fullName = trim("{$user->first_name} {$user->middle_name} {$user->last_name}");

            foreach ($cartItems as $cartItem) {
                $product = Product::find($cartItem->product_id);

                if (!$product) {
                    continue; // Skip if product was deleted
                }

                $cartStatuses[] = [
                    'id' => $cartItem->id,
                    'product_name' => $product->product_name,
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'unit' => $product->unit ?? 'unit',
                    'price' => number_format($cartItem->price, 2),
                    'item_total' => number_format($cartItem->item_total, 2),
                    'product_img' => $product->product_img,
                    'product_description' => $product->description ?? 'No description available',
                    'shipping_address' => $user->delivery_address ?? 'N/A',
                    'name' => $fullName,
                    'phone_number' => $user->phone_number,
                ];
            }

            return response()->json([
                'isSuccess' => true,
                'message' => 'Checked out cart items retrieved successfully.',
                'cart_statuses' => $cartStatuses,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'An error occurred while retrieving cart statuses.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMyPublishedProducts()
    {
        $account = Auth::user(); // Get the currently authenticated user

        if (!$account) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $products = Product::where('account_id', $account->id)
            ->where('visibility', 'Published')
            ->where('is_archived', 0)
            ->select('id', 'product_name', 'price', 'stocks', 'product_img') // Replace with actual image column
            ->get();

        return response()->json([
            'isSuccess' => true,
            'products' => $products,
        ]);
    }

    //     public function checkout(Request $request)
    // {
    //     $user = Auth::user();

    //     if (!$user) {
    //         $response = [
    //             'isSuccess' => false,
    //             'message' => 'User not authenticated',
    //         ];
    //         $this->logAPICalls('checkout', "", $request->all(), [$response]);
    //         return response()->json($response, 401);
    //     }

    //     try {
    //         // Validate that selected products are provided
    //         $validated = $request->validate([
    //             'product_ids' => 'required|array',
    //             'product_ids.*' => 'integer|exists:products,id',
    //         ]);

    //         $selectedProductIds = $validated['product_ids'];
    //         $cartItems = Cart::where('account_id', $user->id)
    //                          ->whereIn('product_id', $selectedProductIds)
    //                          ->get();

    //         if ($cartItems->isEmpty()) {
    //             $response = [
    //                 'isSuccess' => false,
    //                 'message' => 'No valid cart items selected for checkout.',
    //             ];
    //             $this->logAPICalls('checkout', $user->id, $request->all(), [$response]);
    //             return response()->json($response, 400);
    //         }

    //         $totalAmount = 0;
    //         $checkedOutItems = [];

    //         foreach ($cartItems as $item) {
    //             $product = Product::find($item->product_id);

    //             if (!$product) {
    //                 $response = [
    //                     'isSuccess' => false,
    //                     'message' => "Product with ID {$item->product_id} no longer exists.",
    //                 ];
    //                 return response()->json($response, 400);
    //             }

    //             if ($item->quantity > $product->stocks) {
    //                 $response = [
    //                     'isSuccess' => false,
    //                     'message' => "Not enough stock for {$product->product_name}.",
    //                 ];
    //                 return response()->json($response, 400);
    //             }

    //             // Calculate total price for the checked-out items
    //             $itemTotal = $product->price * $item->quantity;
    //             $totalAmount += $itemTotal;

    //             $checkedOutItems[] = [
    //                 'product_id' => $product->id,
    //                 'product_name' => $product->product_name,
    //                 'quantity' => $item->quantity,
    //                 'price' => $product->price,
    //                 'itemTotal' => $itemTotal,
    //             ];

    //             // Deduct stock from the product
    //             $product->stocks -= $item->quantity;
    //             $product->save();

    //             // Remove only the checked-out items from the cart
    //             $item->delete();
    //         }

    //         $response = [
    //             'isSuccess' => true,
    //             'message' => 'Checkout successful. Selected products have been removed from the cart.',
    //             'totalAmount' => $totalAmount,
    //             'checkedOutItems' => $checkedOutItems,
    //         ];

    //         $this->logAPICalls('checkout', $user->id, $request->all(), [$response]);
    //         return response()->json($response, 200);

    //     } catch (Throwable $e) {
    //         $response = [
    //             'isSuccess' => false,
    //             'message' => 'An error occurred during checkout.',
    //             'error' => $e->getMessage(),
    //         ];
    //         $this->logAPICalls('checkout', "", $request->all(), [$response]);
    //         return response()->json($response, 500);
    //     }
    // }

    public function logAPICalls(string $methodName, ?string $userId, array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            // Handle logging error if necessary
            return false;
        }
        return true;
    }
}
