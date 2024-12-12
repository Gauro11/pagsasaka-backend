<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\ApiLog;
use App\Models\Product;
use App\Models\Order;
use App\Models\Cart;
use Illuminate\Support\Facades\Auth;
use Throwable;
use Carbon\Carbon;

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
                'product_img' => 'required|array|min:3',
                'product_img.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'visibility' => 'required|in:Published,Scheduled',
            ]);

            // Ensure the user is authenticated
            if (!auth()->check()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'Unauthorized. Please log in to add a product.',
                ], 500);
            }

            // Get the authenticated user's account ID
            $accountId = auth()->id();

            // Handle image uploads
            $imagePaths = [];
            if ($request->hasFile('product_img')) {
                foreach ($request->file('product_img') as $image) {
                    $path = $image->store('products', 'public');
                    $imagePaths[] = Storage::url($path); // Generate and store public URL
                }
            }

            // Assign the image paths directly (not as a JSON string)
            $validated['product_img'] = $imagePaths;

            // Add the authenticated user's ID to the validated data
            $validated['account_id'] = $accountId;

            // Create the product
            $product = Product::create($validated);

            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message' => 'Product successfully created.',
                'product' => $product,
            ];

            // Log API call
            $this->logAPICalls('addProduct', $product->id, $request->all(), $response);

            return response()->json($response, 200);

        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];

            $this->logAPICalls('addProduct', null, $request->all(), $response);

            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the product.',
                'error' => $e->getMessage(),
            ];

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
                'product_img' => 'sometimes|array',
                'product_img.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'visibility' => 'sometimes|in:Published,Scheduled',
            ]);

            // Handle image uploads if provided
            if ($request->hasFile('product_img')) {
                // Delete old images
                $oldImages = $product->product_img;  // No need to json_decode anymore since it's an array
                if (!empty($oldImages)) {
                    foreach ($oldImages as $oldImage) {
                        $path = str_replace('/storage', 'public', $oldImage); // Convert URL to storage path
                        if (Storage::exists($path)) {
                            Storage::delete($path);
                        }
                    }
                }

                // Upload new images
                $imagePaths = [];
                foreach ($request->file('product_img') as $image) {
                    // Store the new image
                    $path = $image->store('products', 'public');
                    // Store the URL
                    $imagePaths[] = Storage::url($path);
                }

                // Update the validated data with the new image paths
                $validated['product_img'] = $imagePaths;
            }

            // Update the product with the validated data
            $product->update($validated);

            // Prepare success response
            $response = [
                'isSuccess' => true,
                'message' => 'Product successfully updated.',
                'product' => $product,
            ];

            // Log API call
            $this->logAPICalls('editProduct', $product->id, $request->all(), $response);

            return response()->json($response, 200);

        } catch (ModelNotFoundException $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Product not found.',
            ];

            return response()->json($response, 500);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors(),
            ];

            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to update the product.',
                'error' => $e->getMessage(),
            ];

            return response()->json($response, 500);
        }
    }

    public function getAllProducts(Request $request)
    {
        try {
            $searchTerm = $request->input('search', null);
            $perPage = $request->input('per_page', 10);

            $query = Product::select('id', 'product_name', 'description', 'price', 'stocks', 'category_id', 'is_archived')
                ->where('is_archived', '0') // Assuming we only want active products
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                });

            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'No active products found matching the criteria',
                ];
                $this->logAPICalls('getAllProducts', "", $request->all(), $response);
                return response()->json($response, 500);
            }

            $formattedProducts = $result->getCollection()->transform(function ($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stocks' => $product->stocks,
                    'category_id' => $product->category_id,
                    'is_active' => $product->is_archived,
                ];
            });

            $response = [
                'isSuccess' => true,
                'message' => 'Product list retrieved successfully.',
                'products' => $formattedProducts,
                'pagination' => [
                    'total' => $result->total(),
                    'per_page' => $result->perPage(),
                    'current_page' => $result->currentPage(),
                    'last_page' => $result->lastPage(),
                ],
            ];

            $this->logAPICalls('getAllProducts', "", $request->all(), $response);
            return response()->json($response, 200);

        } catch (Throwable $e) {
            // Handle error cases
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve product list.',
                'error' => $e->getMessage()
            ];

            $this->logAPICalls('getAllProducts', "", $request->all(), $response);
            return response()->json($response, 500);
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
                'product' => $product,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'isSuccess' => false,
                'message' => 'Failed to retrieve the product.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getProductsByAccountId(Request $request, $accountId)
    {
        try {
            $searchTerm = $request->input('search', null); // Optional search term
            $perPage = $request->input('per_page', 10); // Items per page (default: 10)

            $query = Product::select('id', 'product_name', 'description', 'price', 'stocks', 'product_img', 'category_id', 'is_archived')
                ->where('account_id', $accountId)
                ->where('is_archived', '0') // Assuming we only want active products
                ->when($searchTerm, function ($query, $searchTerm) {
                    return $query->where(function ($activeQuery) use ($searchTerm) {
                        $activeQuery->where('product_name', 'like', '%' . $searchTerm . '%')
                            ->orWhere('description', 'like', '%' . $searchTerm . '%');
                    });
                });

            $result = $query->paginate($perPage);

            if ($result->isEmpty()) {
                return response()->json([
                    'isSuccess' => false,
                    'message' => 'No products found for the given account ID matching the criteria.',
                ], 404);
            }

            $formattedProducts = $result->getCollection()->transform(function ($product) {
                return [
                    'id' => $product->id,
                    'product_name' => $product->product_name,
                    'description' => $product->description,
                    'price' => $product->price,
                    'stocks' => $product->stocks,
                    'product_img' => $product->product_img,
                    'category_id' => $product->category_id,
                    'is_active' => $product->is_archived == 0,
                ];
            });

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

    public function buyProduct(Request $request, $product_id)
    {
        $user = Auth::user();
    
        if (!$user) {
            $response = [
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ];
            $this->logAPICalls('buyProduct', "", $request->all(), [$response]); // Log the failed API call
            return response()->json($response, 500);
        }
    
        try {
            $validated = $request->validate([
                'quantity' => 'required|integer|min:1',
            ]);
    
            $product = Product::find($product_id);
    
            if (!$product) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Product not found',
                ];
                $this->logAPICalls('buyProduct', "", $request->all(), [$response]); // Log the failed API call
                return response()->json($response, 500);
            }
    
            if ($product->stocks < $validated['quantity']) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Insufficient stock',
                ];
                $this->logAPICalls('buyProduct', $product->id, $request->all(), [$response]); // Log the failed API call
                return response()->json($response, 500);
            }
    
            // Deduct stock
            $product->stocks -= $validated['quantity'];
            $product->save();
    
            // Calculate total price
            $totalAmount = $product->price * $validated['quantity'];
    
            // Create order with 'processing' as the initial status
            $order = Order::create([
                'account_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
                'total_amount' => $totalAmount,
                'status' => 'processing', // Default status set to 'processing'
                'ship_to' => $request->input('ship_to', 'Default Shipping Address'), // Provide a default if not sent
                'created_at' => now()->format('Y-m-d H:i:s'), // Explicitly set created_at
                'updated_at' => now()->format('Y-m-d H:i:s'), // Explicitly set updated_at
            ]);
    
            $response = [
                'isSuccess' => true,
                'message' => 'Order placed successfully',
                'order' => [
                    'id' => $order->id,
                    'account_id' => $order->account_id,
                    'product_id' => $order->product_id,
                    'quantity' => $order->quantity,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status, // Include the status in the response
                    'created_at' => Carbon::parse($order->created_at)->format('F d Y'),
                    'updated_at' => Carbon::parse($order->updated_at)->format('F d Y'),
                ],
            ];
            $this->logAPICalls('buyProduct', $product->id, $request->all(), [$response]); // Log the successful API call
            return response()->json($response, 200);
    
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred while placing the order.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('buyProduct', "", $request->all(), [$response]); // Log the exception
            return response()->json($response, 500);
        }
    }
    
    
    

    public function addToCart(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            $response = [
                'isSuccess' => false,
                'message' => 'User not authenticated',
            ];
            $this->logAPICalls('addToCart', "", $request->all(), [$response]); // Log the failed API call
            return response()->json($response, 500);
        }

        try {
            $validated = $request->validate([
                'product_id' => 'required|integer',
                'quantity' => 'required|integer|min:1',
            ]);

            $product = Product::find($validated['product_id']);

            if (!$product) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Product not found',
                ];
                $this->logAPICalls('addToCart', "", $request->all(), [$response]); // Log the failed API call
                return response()->json($response, 500);
            }

            // Optionally check for maximum stock constraints
            if ($validated['quantity'] > $product->stocks) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'Requested quantity exceeds available stock.',
                ];
                $this->logAPICalls('addToCart', $product->id, $request->all(), [$response]); // Log the failed API call
                return response()->json($response, 500);
            }

            // Create cart entry
            $cart = Cart::create([
                'account_id' => $user->id,
                'product_id' => $product->id,
                'quantity' => $validated['quantity'],
            ]);

            $response = [
                'isSuccess' => true,
                'message' => 'Product added to cart successfully',
                'cart' => $cart,
            ];
            $this->logAPICalls('addToCart', $product->id, $request->all(), [$response]); // Log the successful API call
            return response()->json($response, 200);

        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => 'An error occurred while adding the product to the cart.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('addToCart', "", $request->all(), [$response]); // Log the exception
            return response()->json($response, 500);
        }
    }


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
