<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller as Controller;
use App\Models\ApiLog;
use Carbon\Carbon;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Exception;
use File;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Throwable;



class BaseController extends Controller
{
    /**
     * success response method.
     *
     * @return \Illuminate\Http\Response
     */

    public function createUser(Request $request)
    {
        try {
            $request->validate([
                'name' => ['required', 'string'],
                'email' => ['string', 'required', 'email', 'unique:users,email'],
                'password' => ['required', 'string', 'min:8']
            ]);

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $response = [
                'isSuccess' => true,
                'message' => "You have successfully created a customer."
            ];
            $this->logAPICalls('createUser', "", $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (ValidationException $v) {
            $response = [
                'isSuccess' => false,
                'message' => "Unable to create a user. Please check your inputs.",
                'error' => $v->getMessage()
            ];
            $this->logAPICalls('storeSection', "", $request->all(), [$response]);
            return response()->json($response, 500);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Unable to create a user. Please try again.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('createUser', "", $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function updateUser(Request $request, $id) //$request includes the SessionId/Token
    {
        try {
            //depends on your middleware
            //you should have validation here on the session, get the user_id of that user based on the session(to be pass on the $userId on logApiCalls)
            $user = User::where('id', $id)->first(); //can use firstOrFail()
            if ($user) {
                $user::update([
                    'name' => $request->name,
                    'email' => $request->email
                ]);
            }
            $response = [
                'isSuccess' => true,
                'message' => "You have successfully updated a user."
            ];
            $this->logAPICalls('updateUser', $user, $request->all(), [$response]);
            return response()->json($response, 200);
        } catch (Throwable $e) {
            $response = [
                'isSuccess' => false,
                'message' => "Unable to update a user. Please try again.",
                'error' => $e->getMessage()
            ];
            $this->logAPICalls('updateUser', $user, $request->all(), [$response]);
            return response()->json($response, 500);
        }
    }

    public function getUsers()
    {
        // try
        // {
        //     $users = User::where('status', 'A')->get(); //can use

        //     $response = [
        //         'isSuccess' => true,
        //         'message' => "Customers:",
        //         'data' => $customers ?? []
        //     ];
        //     $this->logAPICalls('getUsers', $userId, [], [$response]);
        //     return response()->json($response, 200);
        // }
        // catch(Throwable $ex)
        // {
        //     $response = [
        //         'isSuccess' => false,
        //         'message'=> "Unable to list users.",
        //         'error'=> $e->getMessage()
        //     ];
        //     $this->logAPICalls('getUsers', "", [], [$response]);
        //     return response()->json($response, 500);
        // }
    }

    public function insertSession(string $code, string $userId)
    {
        $dateTime = Carbon::now();
        $dt = $dateTime->toDateTimeString();
        try {
            Session::create([
                'session_code' => $code,
                'user_id' => $userId,
                'login_date' => $dt,
            ]);
            return true;
        } catch (Throwable $ex) {
            return false;
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
        } catch (Throwable $ex) {
            return false;
        }
        return true;
    }

    public function saveImage($image, string $path, string $empno, string $ln)
    {
        \Log::info('Base64 Image Data: ', ['image' => $image]); // Log the image data
        if (preg_match('/^data:(image|application)/(\w+);base64,/', $image, $type)) {
            $image = substr($image, strpos($image, ',') + 1);
            $type = strtolower($type[2]);

            if (!in_array($type, ['mp4', 'jpg', 'jpeg', 'png', 'svg'])) {
                throw new Exception('The provided file or image is invalid.');
            }

            $image = str_replace(' ', '+', $image);
            $image = base64_decode($image);

            if ($image === false) {
                throw new Exception('Unable to process the image.');
            }
        } else {
            throw new Exception('Please make sure the type you are uploading is an image or PDF file.');
        }

        $dir = 'img/' . $path . '/';
        $file = $empno . '-' . $ln . '.' . $type;
        $absolutePath = public_path($dir);

        if (!File::exists($absolutePath)) {
            File::makeDirectory($absolutePath, 0755, true);
        }

        file_put_contents($absolutePath . $file, $image);

        return $dir . $file; // Return the relative path for URL generation
    }

    public function getSetting(string $code)
    {
        try {
            $value = DB::table('settings')
                ->where('setting_code', $code)
                ->value('setting_value');
        } catch (Throwable $ex) {
            return $ex;
        }
        return $value;
    }
    public function sendResponse($result, $message)
    {
        $response = [
            'isSuccess' => true,
            'message' => $message,
            'data' => $result,
        ];
        return response($response, 200);
    }
    public function test()
    {
        return response()->json([
            'isSuccess' => true,
            'message' => 'test'
        ], 200);
    }
}