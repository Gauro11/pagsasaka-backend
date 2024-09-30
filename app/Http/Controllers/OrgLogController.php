<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrganizationalLog;
use App\Models\ApiLog;
use App\Http\Requests\OrgLogRequest;
use Throwable;
use Illuminate\Validation\ValidationException;

class OrgLogController extends Controller
{
    public function getOrgLog(Request $request)
    {
        try {
            $items = 2;

            // Validation
            $validate = $request->validate([
                'org_id' => 'required'
            ]);

            $perPage = $request->query('per_page', $items);
            $search = $request->input('search');

            // Query building
            $query = OrganizationalLog::where('status', 'A')
                ->where('org_id', $request->org_id);

            // Search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('acronym', 'LIKE', "%{$search}%");
                });
            }

            // Custom handling for org_id == 3
            if ($request->org_id == 3) { // Ensure org_id is integer
                $data = $query->with(['programs:program_entity_id,college_entity_id'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);

                // Manipulate the response to get the name of the college.
                $data->getCollection()->transform(function ($item) {
                    foreach ($item->programs as $program) {
                        $college = OrganizationalLog::find($program->college_entity_id);
                        $program->college_name = $college ? $college->name : null;
                    }
                    return $item;
                });
            } else {
                $data = $query->orderBy('created_at', 'desc')->paginate($perPage);
            }

            // Logging and response
            $response = [
                'isSuccess' => true,
                'data' => $data
            ];

            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response);
        } 
        catch (ValidationException $e) {
            // Handle validation exceptions
            $response = [
                'isSuccess' => false,
                'message' => "Validation error.",
                'error' => $e->errors()
            ];
            return response()->json($response, 422);
        }
        catch (Throwable $e) {
            // Handle all other exceptions
            $response = [
                'isSuccess' => false,
                'message' => "Please contact support.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('getOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function storeOrgLog(OrgLogRequest $request)
    {
        try {
            // Validation is handled in OrgLogRequest, so no need to call validate again

            if ($this->isExist($request->validated())) {
                $response = [
                    'isSuccess' => false,
                    'message' => 'The organization you are trying to register already exists. Please verify your input and try again.'
                ];

                $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
                return response()->json($response, 422);
            }

            OrganizationalLog::create($request->validated());

            // Store Programs
            if ($request->org_id == 3) {
                $this->storeProgram($request->college_entity_id, $request->validated());
            }

            $response = [
                'isSuccess' => true,
                'message' => "Successfully created."
            ];

            $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
            return response()->json($response);
        } 
        catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Unsuccessfully created. Please check your inputs.",
                'error' => 'An unexpected error occurred: ' . $e->getMessage()
            ];

            $this->logAPICalls('storeOrgLog', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    // Similar updates for updateOrgLog, editOrgLog, and deleteOrgLog...

    public function storeProgram($college_id, $validate)
    {
        $program = OrganizationalLog::where('name', $validate['name'])
                    ->where('acronym', $validate['acronym'])
                    ->where('org_id', $validate['org_id'])
                    ->firstOrFail();

        Program::create([
            'program_entity_id' => $program->id,
            'college_entity_id' => $college_id
        ]);
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
        } 
        catch (Throwable $ex) {
            return false;
        }
        return true;
    }
}
