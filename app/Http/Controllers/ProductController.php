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
use Illuminate\Support\Facades\Auth;
use Throwable;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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

            return response()->json([
                'isSuccess' => true,
                'message' => 'Product retrieved successfully.',
                'product' => [$product],
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
            if (!auth()->check()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in to view products.',
                ], 401);
            }

            // Get the authenticated user's account ID
            $accountId = auth()->id();

            // Get optional query parameters
            $searchTerm = $request->input('search', null); // Optional search term
            $perPage = $request->input('per_page', 10); // Items per page (default: 10)

            // Build the query
            $query = Product::select('id', 'product_name', 'description', 'price', 'stocks', 'unit', 'product_img', 'category_id', 'visibility', 'is_archived')
                ->where('account_id', $accountId)
                ->where('is_archived', '0') // Assuming we only want active products
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('product_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                });

            // Paginate results
            $result = $query->paginate($perPage);

            // Check if results are empty
            if ($result->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No products found for your account matching the criteria.',
                ], 404);
            }

            // Format the products
            $formattedProducts = $result->getCollection()->transform(function ($product) {
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

            // Return the response
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

    

    public function buyNow(Request $request, $id)
    {
        \Log::info("Buy Now Request", [
            'product_id' => $id, // Changed from $product_id to $id
            'quantity' => $request->input('quantity', 1),
            'headers' => $request->headers->all(),
        ]);
    
        $account = $request->user();
        if (!$account) {
            \Log::warning("No authenticated user found in buyNow");
            return response()->json([
                'isSuccess' => false,
                'message' => 'No authenticated account found. Please log in.',
            ], 401);
        }
    
        \Log::info("Authenticated user in buyNow", [
            'id' => $account->id,
            'email' => $account->email,
        ]);
    
        if (!is_numeric($id) || $id <= 0) {
            \Log::warning("Invalid product_id format in buyNow: " . $id);
            return response()->json([
                'isSuccess' => false,
                'message' => "Invalid product ID format: " . $id,
            ], 400);
        }
    
        $product = Product::find($id); // Changed from $product_id to $id
        if (!$product) {
            \Log::info("Product not found for product_id in buyNow: " . $id);
            return response()->json([
                'isSuccess' => false,
                'message' => 'Product not found.',
            ], 404);
        }
    
        $quantity = $request->input('quantity', 1);
        $quantity = max(1, min($quantity, $product->stocks));
    
        $cacheKey = 'purchase_' . $account->id . '_' . $product->id;
        Cache::put($cacheKey, $quantity, now()->addMinutes(60));
        \Log::info("Cached quantity in buyNow", [
            'cache_key' => $cacheKey,
            'quantity' => $quantity,
        ]);
    
        $total = $product->price * $quantity;
    
        return response()->json([
            'isSuccess' => true,
            'message' => 'Checkout successful.',
            'product' => [
                'id' => $product->id,
                'name' => $product->product_name,
                'price' => number_format($product->price, 2),
                'quantity' => $quantity,
                'unit' => $product->unit,
                'total' => number_format($total, 2),
                'product_img' => $product->product_img,
            ],
            'buyer_info' => [
                'name' => $account->first_name . ' ' . $account->last_name,
                'contact_number' => $account->phone_number,
                'delivery_address' => $account->delivery_address,
            ],
        ]);
    }
    




    public function getCheckoutPreview(Request $request, $id)
    {
        \Log::info("Checkout Preview Request", [
            'product_id' => $id, // Changed from $product_id to $id
            'payment_method' => $request->input('payment_method', 'COD'),
            'headers' => $request->headers->all(),
        ]);
    
        $account = $request->user();
        if (!$account) {
            \Log::warning("No authenticated user found in getCheckoutPreview");
            return response()->json([
                'isSuccess' => false,
                'message' => 'No authenticated account found. Please log in.',
            ], 401);
        }
    
        \Log::info("Authenticated user in getCheckoutPreview", [
            'id' => $account->id,
            'email' => $account->email,
        ]);
    
        if (!is_numeric($id) || $id <= 0) {
            \Log::warning("Invalid product_id format in getCheckoutPreview: " . $id);
            return response()->json([
                'isSuccess' => false,
                'message' => "Invalid product ID format: " . $id,
            ], 400);
        }
    
        $product = Product::find($id); // Changed from $product_id to $id
        if (!$product) {
            \Log::info("Product not found for product_id in getCheckoutPreview: " . $id);
            return response()->json([
                'isSuccess' => false,
                'message' => "Product ID " . $id . " not found.",
            ], 404);
        }
    
        $cacheKey = 'purchase_' . $account->id . '_' . $product->id;
        $quantity = Cache::get($cacheKey, 1);
        $quantity = max(1, min($quantity, $product->stocks));
        \Log::info("Retrieved quantity in getCheckoutPreview", [
            'cache_key' => $cacheKey,
            'quantity' => $quantity,
        ]);
    
        $payment_method = $request->input('payment_method', 'COD');
        $subtotal = $product->price * $quantity;
    
        return response()->json([
            'isSuccess' => true,
            'message' => 'Checkout preview loaded.',
            'user_info' => [
                'id' => $account->id,
                'buyer_name' => $account->first_name . ' ' . $account->last_name,
                'email' => $account->email,
                'contact_number' => $account->phone_number,
                'delivery_address' => $account->delivery_address,
            ],
            'product_info' => [
                'id' => $product->id,
                'name' => $product->product_name,
                'price' => number_format($product->price, 2),
                'unit' => $product->unit,
                'quantity' => $quantity,
                'stock_available' => $product->stocks,
                'images' => $product->product_img,
                'description' => $product->description ?? 'No description available',
                'category' => $product->category ?? 'Uncategorized',
                'sku' => $product->sku ?? null,
                'weight' => $product->weight ?? null,
                'dimensions' => $product->dimensions ?? null,
            ],
            'order_summary' => [
                'payment_method' => $payment_method,
                'subtotal' => number_format($subtotal, 2),
                'quantity' => $quantity,
                'total_amount' => number_format($subtotal, 2),
            ],
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
