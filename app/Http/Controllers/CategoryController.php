<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Category;
use App\Models\ApiLog;
use Throwable;


class CategoryController extends Controller
{
    public function createCategory(Request $request)
    {
        try {

            $request->validate([
                'category_name' => 'required|string|unique:categories,category_name',
            ]);

            // Create the category
            $category = Category::create([
                'category_name' => $request->category_name,
            ]);

            // Prepare the success response with division and supervisor details
            $response = [
                'isSuccess' => true,
                'message' => 'Category successfully created.',
                'category' => $category,
            ];

            // Log API call
            $this->logAPICalls('createCategory', $category->id, $request->all(), $response);

            return response()->json($response, 200);

        } catch (ValidationException $v) {
            // Prepare the validation error response
            $response = [
                'isSuccess' => false,
                'message' => 'Validation failed.',
                'errors' => $v->errors()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 500);

        } catch (Throwable $e) {
            // Prepare the error response in case of an exception
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to create the Category.',
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createCategory', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function editCategory(Request $request, $id)
    {
        try {

            $category = Category::findOrFail($id);


            $request->validate([
                'category_name' => 'required|string|unique:categories,category_name',
            ]);

            $category->update([
                'category_name' => $request->category_name ?? $category->category_name,
            ]);


            $response = [
                'isSuccess' => true,
                'message' => "Category successfully updated",
                'category' => $category,
            ];

            $this->logAPICalls('editCategory', $id, $request->all(), $response);
            return response()->json($response, 200);

        } catch (ValidationException $v) {

            $response = [
                'isSuccess' => false,
                'message' => "Validation failed.",
                'errors' => $v->errors(),
            ];
            $this->logAPICalls('editCategory', "", $request->all(), $response);
            return response()->json($response, 500);

        } catch (Throwable $e) {

            $response = [
                'isSuccess' => false,
                'message' => "Failed to edit Category.",
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('editCategory', "", $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    public function getCategory(Request $request)
    {
        try {
            // Validate the request to include a search term
            $validated = $request->validate([
                'search' => 'nullable|string', // New search parameter
            ]);

            // Initialize the query
            $query = Category::select('id', 'category_name')
                ->whereIn('is_archived', ['0']);

            // Apply search if provided
            if (!empty($validated['search'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('category_name', 'like', '%' . $validated['search'] . '%');
                });
            }


            // Get the category
            $category = $query->get();

            // Prepare the response
            $response = [
                'isSuccess' => true,
                'message' => "Categories retrieved successfully.",
                'usertype' => $category
            ];

            // Log API calls
            $this->logAPICalls('getCategory', "", $request->all(), [$response]);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Prepare the error response
            $response = [
                'isSuccess' => false,
                'message' => "Failed to retrieve categories.",
                'error' => $e->getMessage()
            ];

            // Log API calls
            $this->logAPICalls('getCategory', "", $request->all(), [$response]);

            return response()->json($response, 500);
        }
    }

    public function deleteCategory($id)
    {
        try {
            $category = Category::findOrFail($id); // Find or throw 404

            // Check if the category is already archived
            if ($category->is_archived == "1") {
                $response = [
                    'isSuccess' => false,
                    'message' => "Category has already been archived and cannot be deleted again.",
                ];
                $this->logAPICalls('deleteCategory', $id, [], [$response]);
                return response()->json($response, 400); // Return a 400 Bad Request response
            }

            // Archive the category
            $category->update(['is_archived' => "1"]);

            $response = [
                'isSuccess' => true,
                'message' => "Category successfully deleted."
            ];
            $this->logAPICalls('deleteCategory', $id, [], [$response]);
            return response()->json($response, 200);

        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Failed to delete the category.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('deleteCategory', "", [], [$response]);
            return response()->json($response, 500);
        }
    }

    public function dropdownCategory(Request $request)
    {
        try {

            $category = Category::select('id', 'category_name')
                ->where('is_archived', '0')
                ->get();

            $response = [
                'isSuccess' => true,
                'message' => 'Dropdown data retrieved successfully.',
                'category' => $category
            ];

            // Log the API call
            $this->logAPICalls('dropdownCategory', "", [], $response);

            return response()->json($response, 200);
        } catch (Throwable $e) {
            // Handle the error response
            $response = [
                'isSuccess' => false,
                'message' => 'Failed to retrieve dropdown data.',
                'error' => $e->getMessage()
            ];

            // Log the error
            $this->logAPICalls('dropdownCategory', "", [], $response);

            return response()->json($response, 500);
        }
    }

    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp)
            ]);
        } catch (Throwable $e) {
            // Handle logging error if necessary
            return false;
        }
        return true;
    }
}
